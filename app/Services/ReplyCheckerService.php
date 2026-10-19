<?php

namespace App\Services;

use App\Models\LeadEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Marks sent emails as replied by looking through the IMAP inbox.
 *
 * A message counts as a reply when its In-Reply-To / References header carries
 * the Message-ID we set at send time, or - as a fallback for clients that drop
 * those headers - when it comes from the lead's address after we sent.
 *
 * Every user has their own mailbox, so the emails are checked owner by owner,
 * each against that owner's IMAP settings. Which owners are covered follows
 * the caller: a user checks their own, a super admin (or the scheduler, via
 * asSystem) checks everyone's.
 */
class ReplyCheckerService
{
    public function __construct(
        private ImapService $imap,
        private UserAccessService $access,
    ) {}

    /**
     * @return int number of emails newly marked as replied
     * @throws \RuntimeException when no owner's mailbox could be read
     */
    public function check(): int
    {
        $owners = $this->pending()->distinct()->pluck('user_id')->filter()->values();
        $marked = 0;
        $failed = [];

        foreach ($owners as $ownerId) {
            try {
                $marked += $this->access->runAs((int) $ownerId, fn () => $this->checkOwnInbox());
            } catch (\RuntimeException $e) {
                // One user's broken mailbox must not stop everyone else's check.
                Log::warning('ReplyCheckerService: mailbox check failed', ['user_id' => $ownerId, 'error' => $e->getMessage()]);
                $failed[] = $e;
            }
        }

        if ($failed !== [] && count($failed) === $owners->count()) {
            throw $failed[0];
        }

        return $marked;
    }

    /** Sent emails still waiting for a reply, within the acting user's reach. */
    private function pending(): Builder
    {
        return LeadEmail::query()
            ->where('status', 'sent')
            ->whereNull('replied_at')
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now()->subDays(60));
    }

    /** Runs as one owner: their pending emails against their inbox. */
    private function checkOwnInbox(): int
    {
        $pending = $this->pending()->with('lead')->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $messages = $this->imap->recentInboxMessages($pending->min('sent_at'));
        $marked   = 0;

        foreach ($pending as $email) {
            // A reply may come from any address the lead has, not just the primary.
            // A bulk email (to a deal, maybe no lead at all) knows its own address.
            $leadAddresses = array_map('strtolower', array_filter(array_merge($email->lead?->email_list ?? [], [$email->to_email])));

            foreach ($messages as $message) {
                $byHeader = $email->message_id
                    && str_contains($message['in_reply_to'] . ' ' . $message['references'], $email->message_id);

                $byAddress = in_array($message['from'], $leadAddresses, true)
                    && $message['date']->gte($email->sent_at);

                if ($byHeader || $byAddress) {
                    $email->update(['replied_at' => $message['date']]);
                    $marked++;
                    break;
                }
            }
        }

        return $marked;
    }
}
