<?php

namespace App\Console\Commands;

use App\Models\MailSetting;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\SequenceStatus;
use App\Sequencer\Jobs\SendSequenceEmailJob;
use Illuminate\Console\Command;

/**
 * Runs every minute from the scheduler. Finds enrollments whose next_action_at
 * has passed and hands them to the queue. It NEVER sends mail itself.
 *
 *   cron -> schedule:run -> sequencer:dispatch-due -> Redis "emails" queue -> worker -> SMTP
 */
class DispatchDueEnrollmentsCommand extends Command
{
    protected $signature = 'sequencer:dispatch-due';

    protected $description = 'Queue a send job for every active enrollment that is due';

    public function handle(): int
    {
        $now = now();
        $batch = (int) config('sequencer.dispatch.batch_size', 500);
        $lease = (int) config('sequencer.dispatch.lease_seconds', 300);
        $dispatched = 0;

        $due = fn () => SequenceEnrollment::query()
            ->where('status', EnrollmentStatus::Active->value)
            ->where('next_action_at', '<=', $now)
            ->where(fn ($q) => $q->whereNull('dispatch_lease_until')->orWhere('dispatch_lease_until', '<=', $now))
            ->whereIn('sequence_id', Sequence::query()->where('status', SequenceStatus::Active->value)->select('id'));

        // Per account, dispatch no more than its per-minute rate: the surplus stays due and
        // is picked up on later runs instead of being queued just to be deferred.
        $accountIds = $due()->distinct()->pluck('mail_setting_id');

        foreach ($accountIds as $accountId) {
            $rate = $accountId ? (int) MailSetting::whereKey($accountId)->value('rate_limit_per_minute') : 0;
            $cap = $rate > 0 ? min($rate, $batch) : $batch;

            $ids = $due()->where('mail_setting_id', $accountId)->orderBy('next_action_at')->limit($cap)->pluck('id');

            foreach ($ids as $id) {
                // Take the lease atomically: only one scheduler run can win this enrollment.
                $leased = SequenceEnrollment::whereKey($id)
                    ->where(fn ($q) => $q->whereNull('dispatch_lease_until')->orWhere('dispatch_lease_until', '<=', $now))
                    ->update(['dispatch_lease_until' => $now->copy()->addSeconds($lease)]);

                if ($leased === 1) {
                    SendSequenceEmailJob::dispatch($id);
                    $dispatched++;
                }
            }
        }

        $this->info("Dispatched {$dispatched} send job(s).");

        return self::SUCCESS;
    }
}
