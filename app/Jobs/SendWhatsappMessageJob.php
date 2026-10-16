<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsAsOwner;
use App\Models\AppNotification;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use App\Services\WhatsappBulkService;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends one queued message of a bulk WhatsApp send (WhatsappBulkService).
 * Runs as the message's owner: their WhatsApp number, their log.
 */
class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    use RunsAsOwner;

    // One attempt only: if Meta received the request but the answer timed out,
    // a retry would send the same message to the same person twice.
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $messageId,
        public readonly int $userId,
    ) {}

    public function ownerId(): ?int
    {
        return $this->userId;
    }

    public function handle(WhatsappService $whatsapp, WhatsappBulkService $bulk): void
    {
        // Claim it. A cancelled message - or one another worker already took -
        // updates nothing and is skipped.
        $claimed = WhatsappMessage::whereKey($this->messageId)
            ->where('status', WhatsappMessage::STATUS_QUEUED)
            ->update(['status' => WhatsappMessage::STATUS_SENDING]);

        if (! $claimed) {
            return;
        }

        $message = WhatsappMessage::findOrFail($this->messageId);

        try {
            $setting = WhatsappSetting::forActingUser();

            if (! $setting?->canSend()) {
                throw new RuntimeException('The WhatsApp Cloud API is no longer connected for this account.');
            }

            $wamid = $whatsapp->sendTemplate(
                $setting,
                $message->phone,
                (string) $message->template_name,
                (string) $message->template_language,
                $message->params ?? [],
            );

            $message->forceFill(['wamid' => $wamid, 'status' => WhatsappMessage::STATUS_SENT, 'sent_at' => now()])->save();
        } catch (\Throwable $e) {
            $message->forceFill(['status' => WhatsappMessage::STATUS_FAILED, 'error' => $e->getMessage(), 'sent_at' => now()])->save();

            Log::warning('SendWhatsappMessageJob: send failed', ['message_id' => $message->id, 'error' => $e->getMessage()]);
        }

        $this->notifyWhenFinished($message, $bulk);
    }

    /**
     * Not passed through job middleware, so it sets the owner itself. Only an
     * error outside handle()'s own try lands here - leave the message failed,
     * not stuck on "sending".
     */
    public function failed(\Throwable $exception): void
    {
        $this->asOwner(fn () => WhatsappMessage::whereKey($this->messageId)
            ->where('status', WhatsappMessage::STATUS_SENDING)
            ->update(['status' => WhatsappMessage::STATUS_FAILED, 'error' => $exception->getMessage()]));
    }

    /** The last message of a bulk send raises one notification with the totals. */
    private function notifyWhenFinished(WhatsappMessage $message, WhatsappBulkService $bulk): void
    {
        if (! $message->batch_id) {
            return;
        }

        $progress = $bulk->progress($message->batch_id);

        if ($progress['running']) {
            return;
        }

        AppNotification::raise(
            $progress['failed'] > 0 ? 'info' : 'success',
            'WhatsApp bulk send finished',
            "{$progress['accepted']} sent, {$progress['failed']} failed"
                . ($progress['cancelled'] ? ", {$progress['cancelled']} cancelled" : '') . '.',
            route('whatsapp.index', ['batch' => $message->batch_id], false),
        );
    }
}
