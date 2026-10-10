<?php

namespace App\Sequencer\Jobs;

use App\Sequencer\Services\SequenceEmailProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the next step of one enrollment, on the same "emails" queue the Leads
 * module uses. All decisions live in SequenceEmailProcessor; the job only carries
 * the id, so a job that sat in the queue (or was duplicated) never acts on stale data.
 */
class SendSequenceEmailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Infrastructure failures (database outage, worker killed). Business failures never throw. */
    public int $tries = 5;

    public int $timeout = 90;

    /** Release the "one job per enrollment" lock even if the worker dies. */
    public int $uniqueFor = 120;

    public function __construct(public readonly int $enrollmentId)
    {
        $this->onQueue(config('sequencer.queue.send'));
    }

    public function uniqueId(): string
    {
        return 'sequence-send-'.$this->enrollmentId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(SequenceEmailProcessor $processor): void
    {
        $processor->process($this->enrollmentId);
    }

    public function failed(Throwable $e): void
    {
        Log::error('SendSequenceEmailJob failed permanently.', [
            'enrollment_id' => $this->enrollmentId,
            'error' => $e->getMessage(),
        ]);
    }
}
