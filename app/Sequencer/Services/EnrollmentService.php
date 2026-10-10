<?php

namespace App\Sequencer\Services;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\MailSetting;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Sequencer\Enums\ActivityType;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Enums\EmailLogStatus;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\SequenceStatus;
use App\Sequencer\Enums\StopReason;
use App\Sequencer\Events\EnrollmentStopped;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Owns every enrollment state transition. Controllers, Bulks, the API and the
 * reply / bounce / unsubscribe listeners all go through here, so a stop condition
 * behaves identically no matter where it is triggered.
 */
class EnrollmentService
{
    public function __construct(
        private readonly SendingWindowService $window,
        private readonly ActivityRecorder $activity,
    ) {}

    // ── Enrolling ──────────────────────────────────────────────────────────

    public function enroll(Sequence $sequence, Lead $lead, ?MailSetting $account = null): EnrollResult
    {
        if (! $lead->hasEmail()) {
            return new EnrollResult(EnrollResult::SKIPPED, null, 'lead_has_no_email');
        }

        if (! $lead->canReceiveEmail()) {
            return new EnrollResult(EnrollResult::SKIPPED, null, 'lead_'.($lead->contact_status?->value ?? 'inactive'));
        }

        if ($sequence->status === SequenceStatus::Archived) {
            return new EnrollResult(EnrollResult::SKIPPED, null, 'sequence_archived');
        }

        $firstStep = $sequence->activeSteps()->first();
        if (! $firstStep) {
            return new EnrollResult(EnrollResult::SKIPPED, null, 'sequence_has_no_steps');
        }

        $account ??= $sequence->mailSetting ?? MailSetting::defaultAccount();
        if (! $account || ! $account->hasSmtp()) {
            return new EnrollResult(EnrollResult::SKIPPED, null, 'no_mail_account');
        }

        return DB::transaction(function () use ($sequence, $lead, $account, $firstStep) {
            // Serialises enrolments into this sequence, so the duplicate-address check below
            // cannot be raced. Same lock order as StepService: sequence row, then enrollments.
            Sequence::whereKey($sequence->id)->lockForUpdate()->first(['id']);

            $existing = SequenceEnrollment::where('sequence_id', $sequence->id)
                ->where('lead_id', $lead->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status !== EnrollmentStatus::Removed) {
                return new EnrollResult(EnrollResult::ALREADY, $existing);
            }

            // One address gets a sequence once, even when two leads hold it (duplicate rows
            // in Leads) - otherwise the same inbox receives every step twice.
            $sameAddress = SequenceEnrollment::where('sequence_id', $sequence->id)
                ->where('lead_id', '!=', $lead->id)
                ->where('status', '!=', EnrollmentStatus::Removed)
                ->whereHas('lead', fn ($q) => $q->where('email', $lead->email))
                ->exists();

            if ($sameAddress) {
                return new EnrollResult(EnrollResult::SKIPPED, null, 'duplicate_email_in_sequence');
            }

            if ($existing) {
                return $this->reactivate($existing, $sequence, $account);
            }

            $draft = $sequence->status === SequenceStatus::Draft;

            try {
                $enrollment = SequenceEnrollment::create([
                    'sequence_id' => $sequence->id,
                    'lead_id' => $lead->id,
                    'mail_setting_id' => $account->id,
                    'current_step' => 0,
                    'status' => $draft ? EnrollmentStatus::Pending : EnrollmentStatus::Active,
                    'started_at' => now(),
                    'next_action_at' => $draft ? null : $this->nextActionAt($sequence, $firstStep),
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request (double click, API retry) won the race.
                return new EnrollResult(
                    EnrollResult::ALREADY,
                    SequenceEnrollment::where('sequence_id', $sequence->id)->where('lead_id', $lead->id)->first()
                );
            }

            $this->activity->record(ActivityType::Enrolled, "Enrolled in {$sequence->name}", [
                'sequence_id' => $sequence->id, 'lead_id' => $lead->id, 'enrollment_id' => $enrollment->id,
            ]);

            return new EnrollResult(EnrollResult::ENROLLED, $enrollment);
        });
    }

    /** Put a previously removed lead back in, continuing from where they stopped. */
    private function reactivate(SequenceEnrollment $enrollment, Sequence $sequence, MailSetting $account): EnrollResult
    {
        $next = $this->nextStepAfter($sequence, $enrollment->current_step);
        if (! $next) {
            return new EnrollResult(EnrollResult::SKIPPED, $enrollment, 'sequence_has_no_steps');
        }

        $draft = $sequence->status === SequenceStatus::Draft;
        $enrollment->forceFill([
            'status' => $draft ? EnrollmentStatus::Pending : EnrollmentStatus::Active,
            'mail_setting_id' => $account->id,
            'next_action_at' => $draft ? null : $this->nextActionAt($sequence, $next),
            'stopped_at' => null,
            'stop_reason' => null,
            'paused_at' => null,
            'dispatch_lease_until' => null,
        ])->save();

        $this->activity->record(ActivityType::Enrolled, "Re-enrolled in {$sequence->name}", [
            'sequence_id' => $sequence->id, 'lead_id' => $enrollment->lead_id, 'enrollment_id' => $enrollment->id,
        ]);

        return new EnrollResult(EnrollResult::REACTIVATED, $enrollment);
    }

    // ── Scheduling maths ───────────────────────────────────────────────────

    /** When $step becomes due if the clock starts now, pushed into the sending window. */
    public function nextActionAt(Sequence $sequence, SequenceStep $step, ?CarbonInterface $from = null): CarbonImmutable
    {
        $base = CarbonImmutable::instance($from ?? now())->addMinutes($step->delayInMinutes());

        return $this->window->nextAllowed($sequence, $base);
    }

    public function nextStepAfter(Sequence $sequence, int $currentStep): ?SequenceStep
    {
        return $sequence->activeSteps()->where('step_number', '>', $currentStep)->first();
    }

    // ── Pause / resume / stop ──────────────────────────────────────────────

    public function pause(SequenceEnrollment $enrollment): bool
    {
        return DB::transaction(function () use ($enrollment) {
            $row = SequenceEnrollment::lockForUpdate()->find($enrollment->id);
            if (! $row || ! in_array($row->status, [EnrollmentStatus::Active, EnrollmentStatus::Pending], true)) {
                return false;
            }

            $row->forceFill(['status' => EnrollmentStatus::Paused, 'paused_at' => now(), 'dispatch_lease_until' => null])->save();
            $enrollment->setRawAttributes($row->getAttributes(), true);

            $this->activity->record(ActivityType::Paused, 'Enrollment paused', [
                'sequence_id' => $row->sequence_id, 'lead_id' => $row->lead_id, 'enrollment_id' => $row->id,
            ]);

            return true;
        });
    }

    /** Resume from the current step; never restarts at step 1. */
    public function resume(SequenceEnrollment $enrollment): bool
    {
        return DB::transaction(function () use ($enrollment) {
            $row = SequenceEnrollment::lockForUpdate()->with('sequence')->find($enrollment->id);
            if (! $row || $row->status !== EnrollmentStatus::Paused || ! $row->sequence) {
                return false;
            }

            $sequence = $row->sequence;
            $draft = $sequence->status === SequenceStatus::Draft;

            // Keep the remaining wait of the interrupted schedule when it is still in the
            // future; otherwise the step is overdue and goes out at the next allowed time.
            $due = $row->next_action_at;
            $due = (! $due || $due->isPast())
                ? $this->window->nextAllowed($sequence, now())
                : $this->window->nextAllowed($sequence, $due);

            $row->forceFill([
                'status' => $draft ? EnrollmentStatus::Pending : EnrollmentStatus::Active,
                'paused_at' => null,
                'next_action_at' => $draft ? null : $due,
                'dispatch_lease_until' => null,
            ])->save();
            $enrollment->setRawAttributes($row->getAttributes(), true);

            $this->activity->record(ActivityType::Resumed, 'Enrollment resumed', [
                'sequence_id' => $row->sequence_id, 'lead_id' => $row->lead_id, 'enrollment_id' => $row->id,
            ]);

            return true;
        });
    }

    public function remove(SequenceEnrollment $enrollment): bool
    {
        return $this->stop($enrollment, StopReason::ManuallyStopped);
    }

    /**
     * Give a failed enrollment (send error, unknown delivery) another go from its current
     * step. A human decision: for "delivery uncertain" the previous email may have arrived.
     */
    public function retry(SequenceEnrollment $enrollment): bool
    {
        return DB::transaction(function () use ($enrollment) {
            $row = SequenceEnrollment::lockForUpdate()->with('sequence')->find($enrollment->id);
            if (! $row || $row->status !== EnrollmentStatus::Failed || ! $row->sequence) {
                return false;
            }

            $next = $this->nextStepAfter($row->sequence, $row->current_step);
            if (! $next) {
                return false;
            }

            // The failed attempt row for this step is re-armed (UNIQUE enrollment+step means it is reused).
            LeadEmail::where('enrollment_id', $row->id)->where('sequence_step_id', $next->id)
                ->where('status', EmailLogStatus::Failed->value)
                ->update(['status' => EmailLogStatus::Queued->value, 'attempts' => 0]);

            $draft = $row->sequence->status === SequenceStatus::Draft;
            $row->forceFill([
                'status' => $draft ? EnrollmentStatus::Pending : EnrollmentStatus::Active,
                'stop_reason' => null,
                'stopped_at' => null,
                'next_action_at' => $draft ? null : $this->window->nextAllowed($row->sequence, now()),
                'dispatch_lease_until' => null,
            ])->save();
            $enrollment->setRawAttributes($row->getAttributes(), true);

            $this->activity->record(ActivityType::Resumed, 'Enrollment retried after failure', [
                'sequence_id' => $row->sequence_id, 'lead_id' => $row->lead_id, 'enrollment_id' => $row->id,
            ]);

            return true;
        });
    }

    /**
     * End an enrollment for $reason. Idempotent: stopping an already finished
     * enrollment does nothing, so two stop conditions racing is harmless.
     */
    public function stop(SequenceEnrollment $enrollment, StopReason $reason): bool
    {
        $stopped = DB::transaction(function () use ($enrollment, $reason) {
            $row = SequenceEnrollment::lockForUpdate()->find($enrollment->id);
            if (! $row || $row->status->isTerminal()) {
                return null;
            }

            $row->forceFill([
                'status' => $reason->enrollmentStatus(),
                'stop_reason' => $reason->value,
                'stopped_at' => now(),
                'next_action_at' => null,
                'dispatch_lease_until' => null,
            ])->save();

            return $row;
        });

        if (! $stopped) {
            return false;
        }

        $enrollment->setRawAttributes($stopped->getAttributes(), true);
        event(new EnrollmentStopped($stopped, $reason));

        return true;
    }

    /** Stop every open enrollment a lead has (bounce, unsubscribe, status change). */
    public function stopForLead(Lead $lead, StopReason $reason): int
    {
        $count = 0;

        SequenceEnrollment::where('lead_id', $lead->id)
            ->whereIn('status', array_map(fn ($s) => $s->value, EnrollmentStatus::open()))
            ->get()
            ->each(function (SequenceEnrollment $enrollment) use ($reason, &$count) {
                if ($this->stop($enrollment, $reason)) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Change whether a lead may be emailed. Unsubscribed leads can never be switched
     * back by a user: consent withdrawn stays withdrawn. Anything other than active
     * stops the lead's sequences.
     */
    public function setContactStatus(Lead $lead, ContactStatus $status): bool
    {
        if ($lead->contact_status === $status || $lead->contact_status === ContactStatus::Unsubscribed) {
            return false;
        }

        $lead->forceFill([
            'contact_status' => $status,
            'bounced_at' => $status === ContactStatus::Bounced ? now() : $lead->bounced_at,
            'unsubscribed_at' => $status === ContactStatus::Unsubscribed ? now() : $lead->unsubscribed_at,
        ])->save();

        if ($status !== ContactStatus::Active) {
            $this->stopForLead($lead, match ($status) {
                ContactStatus::Bounced => StopReason::EmailBounced,
                ContactStatus::Unsubscribed => StopReason::Unsubscribed,
                default => StopReason::ContactInactive,
            });
        }

        return true;
    }

    public function complete(SequenceEnrollment $enrollment): void
    {
        $enrollment->forceFill([
            'status' => EnrollmentStatus::Completed,
            'completed_at' => now(),
            'next_action_at' => null,
            'dispatch_lease_until' => null,
        ])->save();

        $this->activity->record(ActivityType::SequenceCompleted, 'Sequence completed', [
            'sequence_id' => $enrollment->sequence_id, 'lead_id' => $enrollment->lead_id, 'enrollment_id' => $enrollment->id,
        ]);
    }

    /** A sequence went live: pending enrollments get their first due time. */
    public function activatePending(Sequence $sequence): int
    {
        $count = 0;

        $sequence->enrollments()->where('status', EnrollmentStatus::Pending->value)->get()->each(
            function (SequenceEnrollment $enrollment) use ($sequence, &$count) {
                $next = $this->nextStepAfter($sequence, $enrollment->current_step);

                $enrollment->forceFill([
                    'status' => EnrollmentStatus::Active,
                    'next_action_at' => $next ? $this->nextActionAt($sequence, $next) : now(),
                ])->save();
                $count++;
            }
        );

        return $count;
    }
}
