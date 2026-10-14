<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Services\EmailSenderService;
use App\Services\UserAccessService;
use Illuminate\Console\Command;

class SendEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:  php artisan emails:send
     *         php artisan emails:send --dry-run
     *         php artisan emails:send --user=jane
     */
    protected $signature = 'emails:send
        {--dry-run : Preview which leads would receive emails without actually sending}
        {--user= : Only this account\'s leads (username or id). Default: every account}';

    protected $description = 'Queue outreach emails to all new leads that have an email address';

    public function __construct(
        private readonly EmailSenderService $emailSender,
    ) {
        parent::__construct();
    }

    public function handle(UserAccessService $access): int
    {
        // Every account's leads unless --user narrows it. Each queued email
        // still sends as its lead's owner (SendEmailJob runs as lead->user_id).
        if ($this->option('user') === null) {
            return $access->asSystem(fn () => $this->send());
        }

        $owner = User::resolveForConsole($this->option('user'));

        if (! $owner) {
            $this->error('No such user: ' . $this->option('user'));
            return self::FAILURE;
        }

        return $access->runAs($owner, fn () => $this->send());
    }

    private function send(): int
    {
        $leads = Lead::new()->withEmail()->get();

        if ($leads->isEmpty()) {
            $this->warn('No new leads with email addresses found.');
            return self::SUCCESS;
        }

        $this->info("Found {$leads->count()} lead(s) ready for outreach:");
        $this->newLine();

        // Show a preview table
        $this->table(
            ['ID', 'Company', 'Email', 'Website'],
            $leads->map(fn(Lead $l) => [$l->id, $l->company_name, $l->email, $l->website])->toArray()
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run mode: no emails have been queued.');
            return self::SUCCESS;
        }

        if (! $this->confirm("Queue outreach emails to these {$leads->count()} lead(s)?", true)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $dispatched = $this->emailSender->dispatchAll();

        $this->newLine();
        $this->info("✓ Queued {$dispatched} email job(s).");
        $this->line('  Run <fg=yellow>php artisan queue:work --queue=emails</> to process the queue.');

        return self::SUCCESS;
    }
}
