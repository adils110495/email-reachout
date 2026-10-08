<?php

namespace App\Console\Commands;

use App\Services\ReplyCheckerService;
use Illuminate\Console\Command;

class CheckRepliesCommand extends Command
{
    protected $signature   = 'emails:check-replies';
    protected $description = 'Scan the IMAP inbox and mark sent emails that received a reply';

    public function handle(ReplyCheckerService $checker): int
    {
        try {
            $count = $checker->check();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("{$count} new repl" . ($count === 1 ? 'y' : 'ies') . ' found.');

        return self::SUCCESS;
    }
}
