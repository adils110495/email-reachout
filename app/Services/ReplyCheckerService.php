<?php

namespace App\Services;

use App\Models\InboundMessage;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\MailSetting;
use App\Sequencer\Enums\EmailLogStatus;
use App\Sequencer\Events\BounceDetected;
use App\Sequencer\Events\ReplyDetected;
use App\Sequencer\Exceptions\ImapException;
use App\Sequencer\Imap\InboundEmail;
use App\Sequencer\Services\BounceInfo;
use App\Sequencer\Services\BounceParser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Marks sent emails as replied (and detects bounces) by reading each account's IMAP inbox.
 *
 * Covers every email in lead_emails - ones sent from the Leads compose window and
 * sequence steps alike:
 *
 *   new message -> bounce? -> match the original email -> mark bounced, lead bounced, stop sequences
 *               -> reply?  -> match by In-Reply-To / References (our Message-ID), or as a fallback
 *                             by the lead's address after we sent -> replied_at, lead "replied",
 *                             stop that sequence
 *               -> auto-reply (out of office) -> recorded, ignored
 *
 * Only new mail is read (a UID cursor per account), and every message inspected is
 * recorded in inbound_messages, so nothing is processed twice.
 */
class ReplyCheckerService
{
    public function __construct(
        private ImapService $imap,
        private BounceParser $bounces,
    ) {}

    /**
     * Poll every active account that has IMAP configured.
     *
     * @return int number of emails newly marked as replied
     * @throws \RuntimeException when no account could be read at all
     */
    public function check(): int
    {
        $accounts = MailSetting::active()->get()->filter->hasImap();

        if ($accounts->isEmpty()) {
            throw new \RuntimeException('No IMAP settings configured (Settings > Mail Settings).');
        }

        $replies = 0;
        $errors = [];

        foreach ($accounts as $account) {
            $result = $this->pollAccount($account);
            $replies += $result['replies'];
            if ($result['error']) {
                $errors[] = $account->label().': '.$result['error'];
            }
        }

        if ($errors && count($errors) === $accounts->count()) {
            throw new \RuntimeException(implode(' | ', $errors));
        }

        return $replies;
    }

    /**
     * Poll one account.
     *
     * @return array{fetched: int, replies: int, bounces: int, ignored: int, error: ?string}
     */
    public function pollAccount(MailSetting $account): array
    {
        $result = ['fetched' => 0, 'replies' => 0, 'bounces' => 0, 'ignored' => 0, 'error' => null];

        if (! $account->hasImap()) {
            return $result;
        }

        try {
            $batch = $this->imap->fetchNew(
                $account,
                $account->imap_last_uid,
                $account->imap_uid_validity,
                (int) config('sequencer.imap.lookback_days', 14),
                (int) config('sequencer.imap.max_per_run', 200),
            );
        } catch (ImapException $e) {
            $account->forceFill(['imap_ok' => false, 'imap_last_checked_at' => now(), 'imap_last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
            Log::warning('IMAP poll failed.', ['mail_setting_id' => $account->id, 'error' => $e->getMessage()]);

            return [...$result, 'error' => $e->getMessage()];
        }

        $firstPoll = $account->imap_last_uid === null || $account->imap_uid_validity !== $batch->uidValidity;
        $cursor = $firstPoll ? 0 : (int) $account->imap_last_uid;
        $failed = false;

        foreach ($batch->messages as $mail) {
            try {
                $classification = $this->processMessage($account, $mail, $batch->uidValidity);
            } catch (Throwable $e) {
                // Stop here and keep the cursor before this message, so the next poll retries it.
                Log::error('Failed to process inbound message.', ['mail_setting_id' => $account->id, 'uid' => $mail->uid, 'error' => $e->getMessage()]);
                $result['error'] = $e->getMessage();
                $failed = true;
                break;
            }

            $result['fetched']++;
            $cursor = max($cursor, $mail->uid);

            match ($classification) {
                'reply' => $result['replies']++,
                'bounce' => $result['bounces']++,
                default => $result['ignored']++,
            };
        }

        // A first poll reads only the lookback window: jump the cursor to the end of the
        // mailbox so the next poll never walks the (older) rest of it.
        if (! $failed && ! $batch->truncated && $firstPoll) {
            $cursor = max($cursor, $batch->uidNext - 1);
        }

        $account->forceFill([
            'imap_last_uid' => $failed && $firstPoll && $cursor === 0 ? $account->imap_last_uid : $cursor,
            'imap_uid_validity' => $batch->uidValidity,
            'imap_last_checked_at' => now(),
            'imap_ok' => ! $failed,
            'imap_last_error' => $failed ? mb_substr((string) $result['error'], 0, 500) : null,
        ])->save();

        return $result;
    }

    /**
     * Inspect and act on one message. Returns its classification, or "duplicate"
     * when it had been processed before.
     */
    public function processMessage(MailSetting $account, InboundEmail $mail, int $uidValidity = 0): string
    {
        $classification = 'unmatched';
        $email = null;
        $bounce = null;

        if ($mail->fromEmail !== null && strtolower($mail->fromEmail) === strtolower($account->senderEmail())) {
            // A copy of something we sent (some servers file it in the inbox): not a reply.
        } elseif ($bounce = $this->bounces->parse($mail)) {
            $email = $this->matchByIds($account, $bounce->messageIds) ?? $this->matchByRecipient($account, $bounce->recipient);
            $classification = $email ? ($bounce->hard ? 'bounce' : 'soft_bounce') : 'unmatched';
        } else {
            $email = $this->matchByIds($account, $mail->referencedIds()) ?? $this->matchBySender($account, $mail);
            $classification = $email ? (BounceParser::isAutoReply($mail) ? 'auto_reply' : 'reply') : 'unmatched';
        }

        $events = [];

        $recorded = DB::transaction(function () use ($account, $mail, $uidValidity, $classification, $email, $bounce, &$events) {
            try {
                InboundMessage::create([
                    'mail_setting_id' => $account->id,
                    'folder' => $account->imap_folder ?: 'INBOX',
                    'uid' => $mail->uid,
                    'uid_validity' => $uidValidity,
                    'message_id' => $mail->messageId ? mb_substr($mail->messageId, 0, 255) : null,
                    'from_email' => $mail->fromEmail ? mb_substr($mail->fromEmail, 0, 255) : null,
                    'subject' => mb_substr($mail->subject, 0, 998),
                    'classification' => $classification,
                    'lead_email_id' => $email?->id,
                    'received_at' => $mail->date,
                ]);
            } catch (UniqueConstraintViolationException) {
                return false;   // already processed
            }

            match ($classification) {
                'reply' => $events[] = $this->applyReply($email, $mail),
                'bounce' => $events[] = $this->applyHardBounce($email, $bounce),
                'soft_bounce' => LeadEmail::whereKey($email->id)->update(['error_message' => mb_substr('Soft bounce: '.$bounce->reason, 0, 1000)]),
                default => null,
            };

            return true;
        }, attempts: 3);

        if (! $recorded) {
            return 'duplicate';
        }

        foreach (array_filter($events) as $event) {
            event($event);
        }

        return $classification;
    }

    // ── effects ────────────────────────────────────────────────────────────

    private function applyReply(LeadEmail $email, InboundEmail $mail): ?ReplyDetected
    {
        $first = LeadEmail::whereKey($email->id)->whereNull('replied_at')->update(['replied_at' => $mail->date ?? now()]) === 1;

        // The lead's outreach status follows, as it always has in the Leads module.
        Lead::whereKey($email->lead_id)->update(['status' => Lead::STATUS_REPLIED]);

        return $first ? new ReplyDetected($email->refresh()) : null;
    }

    private function applyHardBounce(LeadEmail $email, BounceInfo $bounce): ?BounceDetected
    {
        $first = LeadEmail::whereKey($email->id)->whereNull('bounced_at')->update([
            'bounced_at' => now(),
            'status' => EmailLogStatus::Bounced->value,
            'error_message' => mb_substr($bounce->reason, 0, 1000),
        ]) === 1;

        $lead = Lead::find($email->lead_id);

        return ($first && $lead) ? new BounceDetected($lead, $email->refresh(), $bounce->reason) : null;
    }

    // ── matching ───────────────────────────────────────────────────────────

    /** Emails sent from this account (or, for older rows, with no account recorded). */
    private function fromAccount(MailSetting $account)
    {
        return LeadEmail::query()
            ->where(fn ($q) => $q->where('mail_setting_id', $account->id)->orWhereNull('mail_setting_id'))
            ->whereIn('status', [EmailLogStatus::Sent->value, EmailLogStatus::Bounced->value]);
    }

    /** @param  list<string>  $ids */
    private function matchByIds(MailSetting $account, array $ids): ?LeadEmail
    {
        if ($ids === []) {
            return null;
        }

        return $this->fromAccount($account)->whereIn('message_id', array_slice($ids, 0, 100))->latest('sent_at')->first();
    }

    /** Bounce without a usable Message-ID: fall back to the failed recipient's latest email. */
    private function matchByRecipient(MailSetting $account, ?string $recipient): ?LeadEmail
    {
        if (! $recipient) {
            return null;
        }

        return $this->fromAccount($account)
            ->where('to_email', strtolower($recipient))
            ->where('sent_at', '>=', now()->subDays(30))
            ->latest('sent_at')
            ->first();
    }

    /**
     * Some mail clients drop In-Reply-To / References. Fallback: a message from one of
     * the lead's addresses, received after we emailed them (the original Leads rule).
     */
    private function matchBySender(MailSetting $account, InboundEmail $mail): ?LeadEmail
    {
        if (! $mail->fromEmail) {
            return null;
        }

        $leadIds = Lead::holdingAddress($mail->fromEmail)->pluck('id');
        if ($leadIds->isEmpty()) {
            return null;
        }

        return $this->fromAccount($account)
            ->whereIn('lead_id', $leadIds)
            ->where('status', EmailLogStatus::Sent->value)
            ->where('sent_at', '>=', now()->subDays(60))
            ->when($mail->date, fn ($q) => $q->where('sent_at', '<=', $mail->date))
            ->latest('sent_at')
            ->first();
    }
}
