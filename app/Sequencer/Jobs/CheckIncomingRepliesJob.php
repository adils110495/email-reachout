<?php

namespace App\Sequencer\Jobs;

use App\Models\MailSetting;
use App\Services\ReplyCheckerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scheduler -> emails:check-replies -> CheckIncomingRepliesJob -> ReplyCheckerService -> IMAP.
 * One poll per account at a time (unique), so mailbox cursors never race.
 */
class CheckIncomingRepliesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 110;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $mailSettingId)
    {
        $this->onQueue(config('sequencer.queue.imap'));
    }

    public function uniqueId(): string
    {
        return 'imap-poll-'.$this->mailSettingId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ReplyCheckerService $checker): void
    {
        $account = MailSetting::active()->find($this->mailSettingId);

        if (! $account || ! $account->hasImap()) {
            return;
        }

        $result = $checker->pollAccount($account);

        if ($result['fetched'] > 0 || $result['error']) {
            Log::info('IMAP poll finished.', ['mail_setting_id' => $account->id] + $result);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('CheckIncomingRepliesJob failed.', ['mail_setting_id' => $this->mailSettingId, 'error' => $e->getMessage()]);
    }
}
