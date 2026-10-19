<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsAsOwner;
use App\Models\WhatsappSetting;
use App\Services\WhatsappSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A heavy coexistence webhook - a chunk of chat history, or a contacts sync -
 * applied off-request so the webhook can answer Meta straight away. Runs as
 * the number's owner. Safe to retry: everything it writes is idempotent.
 */
class ApplyWhatsappSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    use RunsAsOwner;

    public int $tries = 3;

    public int $backoff = 30;

    /** Below the worker's --timeout=90 (docs/queue-jobs.md). */
    public int $timeout = 85;

    public function __construct(
        public readonly int $userId,
        public readonly string $field,
        public readonly array $value,
    ) {}

    public function ownerId(): ?int
    {
        return $this->userId;
    }

    public function handle(WhatsappSyncService $sync): void
    {
        $setting = WhatsappSetting::forActingUser() ?? WhatsappSetting::mine()->createOrFirst();

        match ($this->field) {
            'history'            => $sync->applyHistory($this->value, $setting),
            'smb_app_state_sync' => $sync->applyStateSync($this->value, $setting),
            default              => null,
        };
    }
}
