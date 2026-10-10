<?php

namespace App\Sequencer\Services;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\MailSetting;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Enums\EmailLogStatus;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\SendOutcome;
use App\Sequencer\Enums\StopReason;
use App\Sequencer\Events\BounceDetected;
use App\Sequencer\Events\EmailFailed;
use App\Sequencer\Events\EmailQueued;
use App\Sequencer\Events\EmailSent;
use App\Sequencer\Exceptions\RecipientRejectedException;
use App\Sequencer\Exceptions\SendException;
use App\Sequencer\Exceptions\TransientSendException;
use App\Sequencer\Mail\EmailProviderManager;
use App\Services\ImapService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The heart of the sequencer: sends the next step of one enrollment, safely.
 *
 *   PHASE A  (one DB transaction, enrollment row locked FOR UPDATE)
 *            re-check every stop condition, window, rate, daily limits; create the
 *            lead_emails row (UNIQUE enrollment+step) in status "sending"; render.
 *   PHASE B  (no locks held)  the SMTP call.
 *   PHASE C  mark the email "sent" immediately, then advance the enrollment.
 *
 * Duplicate protection, in layers:
 *   1. unique job id per enrollment (ShouldBeUnique)       - cheap, best effort
 *   2. scheduler lease on the enrollment row               - stops scheduler overlap
 *   3. SELECT ... FOR UPDATE on the enrollment             - serialises workers
 *   4. UNIQUE(enrollment_id, sequence_step_id) on lead_emails - the real guarantee:
 *      a second send attempt for the same step cannot even be inserted
 *   5. an email already sent/sending is never sent again (retries, restarts, crashes)
 *
 * The trade-off is deliberate: at-most-once. If a worker dies after the SMTP call
 * but before "sent" is recorded, the step is NOT re-sent (see stale_after_minutes).
 */
class SequenceEmailProcessor
{
    public function __construct(
        private readonly EmailProviderManager $providers,
        private readonly SendingWindowService $window,
        private readonly DailyLimitService $daily,
        private readonly SendRateLimiter $rate,
        private readonly EmailComposer $composer,
        private readonly EnrollmentService $enrollments,
        private readonly ImapService $imap,
    ) {}

    public function process(int $enrollmentId): SendOutcome
    {
        $claim = $this->claim($enrollmentId);

        if ($claim instanceof SendOutcome) {
            return $claim;
        }

        event(new EmailQueued($claim->log));

        // ── PHASE B: deliver (no database locks held during network I/O) ──
        try {
            $this->providers->forAccount($claim->account)->send($claim->email);
        } catch (SendException $e) {
            return $this->handleFailure($claim, $e);
        } catch (Throwable $e) {
            return $this->handleFailure($claim, new TransientSendException($e->getMessage(), 0, $e));
        }

        // ── PHASE C ──
        $this->markSent($claim->log->id);
        $outcome = $this->finalize($claim);
        $this->copyToSentFolder($claim);

        return $outcome;
    }

    // ───────────────────────────── PHASE A ──────────────────────────────────

    private function claim(int $enrollmentId): SendClaim|SendOutcome
    {
        // Retries the whole transaction if MySQL picks it as a deadlock victim under heavy contention.
        return DB::transaction(fn () => $this->claimInTransaction($enrollmentId), attempts: 5);
    }

    private function claimInTransaction(int $enrollmentId): SendClaim|SendOutcome
    {
        $now = CarbonImmutable::now();

        $enrollment = SequenceEnrollment::lockForUpdate()->find($enrollmentId);
        if (! $enrollment) {
            return SendOutcome::Skipped;
        }

        // Never trust stale queue data: everything is re-read under the lock.
        if ($enrollment->status !== EnrollmentStatus::Active) {
            return $this->release($enrollment, SendOutcome::Skipped);
        }

        if (! $enrollment->next_action_at || $enrollment->next_action_at->gt($now)) {
            return $this->release($enrollment, SendOutcome::Skipped);
        }

        // Stop conditions first: unsubscribe / bounce / unusable lead always win.
        $lead = Lead::find($enrollment->lead_id);
        if (! $lead) {
            $this->enrollments->stop($enrollment, StopReason::ContactRemoved);

            return SendOutcome::Stopped;
        }

        if (! $lead->canReceiveEmail()) {
            $this->enrollments->stop($enrollment, match ($lead->contact_status) {
                ContactStatus::Unsubscribed => StopReason::Unsubscribed,
                ContactStatus::Bounced => StopReason::EmailBounced,
                default => StopReason::ContactInactive,
            });

            return SendOutcome::Stopped;
        }

        $sequence = Sequence::with('creator')->find($enrollment->sequence_id);
        if (! $sequence || ! $sequence->isActive()) {
            return $this->release($enrollment, SendOutcome::Skipped);   // paused / draft / archived: send nothing
        }

        $account = $enrollment->mail_setting_id ? MailSetting::find($enrollment->mail_setting_id) : null;
        if (! $account || ! $account->hasSmtp()) {
            $this->enrollments->stop($enrollment, StopReason::SendFailed);

            return SendOutcome::Failed;
        }

        if (! $account->is_active) {
            return $this->defer($enrollment, $now->addMinutes(15));   // account switched off: wait, do not fail
        }

        $step = $this->enrollments->nextStepAfter($sequence, $enrollment->current_step);
        if (! $step) {
            $this->enrollments->complete($enrollment);

            return SendOutcome::Completed;
        }

        // Idempotency: has this enrollment/step been attempted before?
        // Plain read on purpose: every writer of this enrollment's emails holds the enrollment row
        // lock we already own. A locking read on a not-yet-existing unique key would take gap locks
        // that deadlock with concurrent workers inserting neighbouring keys.
        $log = LeadEmail::where('enrollment_id', $enrollment->id)->where('sequence_step_id', $step->id)->first();

        if ($log) {
            if ($log->wasSent()) {
                $this->advanceAfterSend($enrollment, $sequence, $step->step_number);   // repair progress after a crash

                return SendOutcome::Recovered;
            }

            if ($log->status === EmailLogStatus::Sending->value) {
                $staleAfter = (int) config('sequencer.send.stale_after_minutes', 15);

                if ($log->claimed_at && $log->claimed_at->gt($now->subMinutes($staleAfter))) {
                    return SendOutcome::Skipped;   // another worker is mid-send; leave its lease alone
                }

                // Worker died mid-send. The SMTP server may or may not have accepted it,
                // so we refuse to risk a duplicate: stop and let a human decide.
                $log->forceFill([
                    'status' => EmailLogStatus::Failed->value,
                    'error_message' => 'Worker stopped while sending; delivery outcome unknown. Not re-sent to avoid a duplicate.',
                ])->save();
                $this->enrollments->stop($enrollment, StopReason::DeliveryUncertain);
                event(new EmailFailed($log, (string) $log->error_message));

                return SendOutcome::Failed;
            }

            if ($log->status === EmailLogStatus::Failed->value) {
                $this->enrollments->stop($enrollment, StopReason::SendFailed);

                return SendOutcome::Failed;
            }
            // status "queued" = a previous transient failure waiting for retry: fall through.
        }

        // Sending window (sequence timezone + days + hours).
        if (! $this->window->isWithinWindow($sequence, $now)) {
            return $this->defer($enrollment, $this->window->nextAllowed($sequence, $now));
        }

        // Per-account speed limit (shared across workers). A cache outage must not lose or duplicate mail.
        try {
            $wait = $this->rate->acquire($account);
        } catch (Throwable $e) {
            Log::warning('Sequencer rate limiter unavailable; deferring send.', ['enrollment_id' => $enrollment->id, 'error' => $e->getMessage()]);
            $wait = 60;
        }
        if ($wait > 0) {
            return $this->defer($enrollment, $now->addSeconds($wait));
        }

        // Daily limits (atomic in the database).
        $sequenceDay = $this->window->dayKey($sequence, $now);
        $accountDay = $now->setTimezone(config('app.timezone'))->toDateString();

        if (! $this->daily->reserve(DailyLimitService::SCOPE_SEQUENCE, $sequence->id, $sequenceDay, $sequence->daily_limit)) {
            return $this->defer($enrollment, $this->window->nextAllowed($sequence, $this->window->startOfNextDay($sequence, $now)));
        }

        if (! $this->daily->reserve(DailyLimitService::SCOPE_ACCOUNT, $account->id, $accountDay, $account->daily_limit)) {
            $this->daily->release(DailyLimitService::SCOPE_SEQUENCE, $sequence->id, $sequenceDay, $sequence->daily_limit);
            $tomorrow = $now->setTimezone(config('app.timezone'))->addDay()->startOfDay();

            return $this->defer($enrollment, $this->window->nextAllowed($sequence, $tomorrow));
        }

        // Claim the step. UNIQUE(enrollment_id, sequence_step_id) is the final guard.
        try {
            if ($log) {
                $log->forceFill([
                    'status' => EmailLogStatus::Sending->value,
                    'claimed_at' => $now,
                    'attempts' => $log->attempts + 1,
                    'error_message' => null,
                ])->save();
            } else {
                $log = LeadEmail::create([
                    'lead_id' => $lead->id,
                    'sequence_id' => $sequence->id,
                    'sequence_step_id' => $step->id,
                    'enrollment_id' => $enrollment->id,
                    'mail_setting_id' => $account->id,
                    'message_id' => $this->newMessageId($account),
                    'tracking_token' => Str::random(40),
                    'from_email' => $account->senderEmail(),
                    'to_email' => $lead->email,
                    'subject' => mb_substr($step->subject, 0, 255),
                    'body' => '',
                    'status' => EmailLogStatus::Sending->value,
                    'attempts' => 1,
                    'claimed_at' => $now,
                ]);
            }
        } catch (UniqueConstraintViolationException) {
            $this->releaseSlots($sequence, $account, $sequenceDay, $accountDay);

            return SendOutcome::Skipped;   // someone else holds this step
        }

        try {
            $email = $this->composer->compose($log, $step, $lead, $account, $sequence);
        } catch (Throwable $e) {
            // A template that cannot be rendered will never succeed: fail the step, keep the evidence.
            $this->releaseSlots($sequence, $account, $sequenceDay, $accountDay);
            $log->forceFill(['status' => EmailLogStatus::Failed->value, 'error_message' => 'Could not render email: '.$e->getMessage()])->save();
            $this->enrollments->stop($enrollment, StopReason::SendFailed);
            event(new EmailFailed($log, (string) $log->error_message));

            return SendOutcome::Failed;
        }

        $log->forceFill(['subject' => mb_substr($email->subject, 0, 255), 'body' => $email->html])->save();

        return new SendClaim($enrollment->id, $log, $lead, $sequence, $step, $account, $email, $sequenceDay, $accountDay);
    }

    // ───────────────────────────── PHASE C ──────────────────────────────────

    /** Record the send immediately and on its own, so the window where "sent" is unknown is a single UPDATE. */
    private function markSent(int $logId): void
    {
        $attempt = 0;

        while (true) {
            try {
                LeadEmail::whereKey($logId)->where('status', EmailLogStatus::Sending->value)->update([
                    'status' => EmailLogStatus::Sent->value,
                    'sent_at' => now(),
                    'error_message' => null,
                ]);

                return;
            } catch (QueryException $e) {
                if (++$attempt >= 5) {
                    Log::critical('Email was sent but could not be marked sent.', ['lead_email_id' => $logId, 'error' => $e->getMessage()]);
                    throw $e;
                }
                usleep(200_000 * $attempt);
            }
        }
    }

    private function finalize(SendClaim $claim): SendOutcome
    {
        $outcome = DB::transaction(function () use ($claim) {
            $enrollment = SequenceEnrollment::lockForUpdate()->find($claim->enrollmentId);

            return $enrollment
                ? $this->advanceAfterSend($enrollment, $claim->sequence, $claim->step->step_number)
                : SendOutcome::Sent;
        }, attempts: 3);

        // The lead's outreach status follows, as in the Leads module (never downgrades "replied").
        Lead::whereKey($claim->lead->id)->whereIn('status', [Lead::STATUS_NEW, Lead::STATUS_FAILED])->update(['status' => Lead::STATUS_SENT]);

        event(new EmailSent($claim->log->refresh()));

        return $outcome;
    }

    /** Best effort, like the Leads compose window: the mailbox's Sent folder shows the email. */
    private function copyToSentFolder(SendClaim $claim): void
    {
        if (! $claim->account->hasImap() || blank($claim->account->folder)) {
            return;
        }

        try {
            $this->imap->copyToSentFolder(
                to: (string) $claim->log->to_email,
                subject: $claim->email->subject,
                htmlBody: $claim->email->html,
                fromName: $claim->email->fromName,
                fromEmail: $claim->email->fromEmail,
                account: $claim->account,
                messageId: $claim->email->messageId,
            );
        } catch (Throwable $e) {
            Log::warning('Could not copy sequence email to the Sent folder.', ['lead_email_id' => $claim->log->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * After step N went out: move current_step forward and either schedule the next
     * step or complete the enrollment. Safe to call again (repairs after a crash).
     */
    private function advanceAfterSend(SequenceEnrollment $enrollment, Sequence $sequence, int $stepNumber): SendOutcome
    {
        $enrollment->current_step = max($enrollment->current_step, $stepNumber);
        $enrollment->dispatch_lease_until = null;

        $next = $this->enrollments->nextStepAfter($sequence, $enrollment->current_step);

        if (! $enrollment->status->isOpen()) {
            $enrollment->save();      // stopped while the email was in flight: keep that status

            return SendOutcome::Sent;
        }

        if ($next) {
            $enrollment->next_action_at = $this->enrollments->nextActionAt($sequence, $next);
            $enrollment->save();

            return SendOutcome::Sent;
        }

        $enrollment->save();

        if ($enrollment->status === EnrollmentStatus::Active) {
            $this->enrollments->complete($enrollment);

            return SendOutcome::Completed;
        }

        $enrollment->forceFill(['next_action_at' => null])->save();   // paused on its last step

        return SendOutcome::Sent;
    }

    // ───────────────────────────── Failures ─────────────────────────────────

    private function handleFailure(SendClaim $claim, SendException $e): SendOutcome
    {
        $message = mb_substr($e->getMessage(), 0, 1000);
        $maxTries = (int) config('sequencer.send.tries', 5);

        [$outcome, $log] = DB::transaction(function () use ($claim, $e, $message, $maxTries) {
            $enrollment = SequenceEnrollment::lockForUpdate()->find($claim->enrollmentId);
            $log = LeadEmail::lockForUpdate()->find($claim->log->id);

            // Nothing left our server: hand the quota back.
            $this->releaseSlots($claim->sequence, $claim->account, $claim->sequenceDay, $claim->accountDay);

            if ($e instanceof RecipientRejectedException) {
                $log->forceFill(['status' => EmailLogStatus::Bounced->value, 'bounced_at' => now(), 'error_message' => $message])->save();

                return [SendOutcome::Bounced, $log];
            }

            if ($e->isPermanent() || $log->attempts >= $maxTries) {
                $log->forceFill(['status' => EmailLogStatus::Failed->value, 'error_message' => $message])->save();
                if ($enrollment) {
                    $this->enrollments->stop($enrollment, StopReason::SendFailed);
                }

                return [SendOutcome::Failed, $log];
            }

            // Transient: back to "queued" and retry later with exponential-ish backoff.
            $log->forceFill(['status' => EmailLogStatus::Queued->value, 'error_message' => $message])->save();
            if ($enrollment) {
                $backoff = (array) config('sequencer.send.backoff', [30, 120, 600, 1800]);
                $delay = $backoff[min($log->attempts - 1, count($backoff) - 1)] ?? 600;
                $enrollment->forceFill([
                    'next_action_at' => now()->addSeconds($delay),
                    'dispatch_lease_until' => null,
                ])->save();
            }

            return [SendOutcome::Retry, $log];
        }, attempts: 3);

        Log::log($outcome === SendOutcome::Retry ? 'warning' : 'error', 'Sequence email '.$outcome->value, [
            'lead_email_id' => $log->id,
            'enrollment_id' => $claim->enrollmentId,
            'attempt' => $log->attempts,
            'error' => $message,
        ]);

        if ($outcome === SendOutcome::Bounced) {
            if ($lead = Lead::find($log->lead_id)) {
                event(new BounceDetected($lead, $log, $message));
            }
        } elseif ($outcome === SendOutcome::Failed) {
            Lead::whereKey($log->lead_id)->where('status', Lead::STATUS_NEW)->update(['status' => Lead::STATUS_FAILED]);
            event(new EmailFailed($log, $message));
        }

        return $outcome;
    }

    // ───────────────────────────── helpers ──────────────────────────────────

    private function release(SequenceEnrollment $enrollment, SendOutcome $outcome): SendOutcome
    {
        if ($enrollment->dispatch_lease_until !== null) {
            $enrollment->forceFill(['dispatch_lease_until' => null])->save();
        }

        return $outcome;
    }

    private function defer(SequenceEnrollment $enrollment, \DateTimeInterface $until): SendOutcome
    {
        $enrollment->forceFill(['next_action_at' => $until, 'dispatch_lease_until' => null])->save();

        return SendOutcome::Deferred;
    }

    private function releaseSlots(Sequence $sequence, MailSetting $account, string $sequenceDay, string $accountDay): void
    {
        $this->daily->release(DailyLimitService::SCOPE_SEQUENCE, $sequence->id, $sequenceDay, $sequence->daily_limit);
        $this->daily->release(DailyLimitService::SCOPE_ACCOUNT, $account->id, $accountDay, $account->daily_limit);
    }

    /** Globally unique RFC 5322 Message-ID (no angle brackets), on the sender's own domain. */
    private function newMessageId(MailSetting $account): string
    {
        $domain = strtolower(substr(strrchr($account->senderEmail(), '@') ?: '@localhost', 1)) ?: 'localhost';

        return sprintf('%s.%s@%s', now()->format('YmdHis'), bin2hex(random_bytes(12)), $domain);
    }
}
