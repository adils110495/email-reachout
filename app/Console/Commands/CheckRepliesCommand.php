<?php

namespace App\Console\Commands;

use App\Models\MailSetting;
use App\Sequencer\Jobs\CheckIncomingRepliesJob;
use App\Services\ReplyCheckerService;
use Illuminate\Console\Command;

/**
 * Reply + bounce detection for every sent email (Leads compose and sequences).
 * Scheduled every two minutes: queues one poll per IMAP account. --sync polls inline.
 */
class CheckRepliesCommand extends Command
{
    protected $signature   = 'emails:check-replies
                              {--account= : Only this mail settings account id}
                              {--sync : Poll inline instead of queueing (debugging)}';

    protected $description = 'Scan the IMAP inboxes and mark sent emails that received a reply or bounced';

    public function handle(ReplyCheckerService $checker): int
    {
        $accounts = MailSetting::active()
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter->hasImap();

        if ($accounts->isEmpty()) {
            $this->warn('No active account has IMAP configured (Settings > Mail Settings).');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            if ($this->option('sync')) {
                $r = $checker->pollAccount($account);
                $this->line("#{$account->id} {$account->label()}: fetched {$r['fetched']}, replies {$r['replies']}, bounces {$r['bounces']}".($r['error'] ? " (error: {$r['error']})" : ''));
            } else {
                CheckIncomingRepliesJob::dispatch($account->id);
            }
        }

        $this->info($accounts->count().' account(s) '.($this->option('sync') ? 'polled.' : 'queued.'));

        return self::SUCCESS;
    }
}
