<?php

namespace App\Console\Commands;

use App\Services\ReplyCheckerService;
use App\Services\UserAccessService;
use Illuminate\Console\Command;

class CheckRepliesCommand extends Command
{
    protected $signature   = 'emails:check-replies';
    protected $description = 'Scan every user\'s IMAP inbox and mark sent emails that received a reply';

    public function handle(ReplyCheckerService $checker, UserAccessService $access): int
    {
        try {
            // System-wide on purpose: the scheduler covers every user's emails.
            // The service still reads each owner's inbox as that owner.
            $count = $access->asSystem(fn () => $checker->check());
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("{$count} new repl" . ($count === 1 ? 'y' : 'ies') . ' found.');

        return self::SUCCESS;
    }
}
