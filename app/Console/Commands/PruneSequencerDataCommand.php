<?php

namespace App\Console\Commands;

use App\Models\DailySendCounter;
use App\Models\InboundMessage;
use Illuminate\Console\Command;

/** Daily housekeeping: drop data that has no further use. */
class PruneSequencerDataCommand extends Command
{
    protected $signature = 'sequencer:prune';

    protected $description = 'Delete old daily send counters and processed-inbox records';

    public function handle(): int
    {
        $counters = DailySendCounter::where('day', '<', now()->subDays(60)->toDateString())->delete();

        // Six months of inbound records: enough to de-duplicate even after a cursor reset.
        $inbound = InboundMessage::where('created_at', '<', now()->subDays(180))->delete();

        $this->info("Pruned {$counters} counter(s) and {$inbound} inbound record(s).");

        return self::SUCCESS;
    }
}
