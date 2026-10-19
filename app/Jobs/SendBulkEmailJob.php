<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsAsOwner;
use App\Mail\OutreachMail;
use App\Models\AppNotification;
use App\Models\AppSetting;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Services\EmailBulkService;
use App\Services\ImapService;
use App\Services\MailConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends one queued email of a bulk send (EmailBulkService).
 * Runs as the email's owner: their SMTP, their Sent folder, their activity log.
 */
class SendBulkEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    use RunsAsOwner;

    // One attempt only: if the SMTP server took the message but the answer
    // timed out, a retry would send the same email to the same person twice.
    public int $tries = 1;

    // Below the worker's --timeout=90, so failed() still runs (docs/queue-jobs.md).
    public int $timeout = 85;

    public function __construct(
        public readonly int $emailId,
        public readonly int $userId,
    ) {}

    public function ownerId(): ?int
    {
        return $this->userId;
    }

    public function handle(MailConfigService $mailConfig, ImapService $imap, EmailBulkService $bulk): void
    {
        // Claim it. A cancelled email - or one another worker already took -
        // updates nothing and is skipped.
        $claimed = LeadEmail::whereKey($this->emailId)
            ->where('status', LeadEmail::STATUS_QUEUED)
            ->update(['status' => LeadEmail::STATUS_SENDING]);

        if (! $claimed) {
            return;
        }

        $email = LeadEmail::findOrFail($this->emailId);

        AppSetting::flush(); // long-lived worker: pick up changes made under Settings > Branding
        $senderCompany = AppSetting::companyName();
        $senderName    = $mailConfig->fromName((string) config('mail.from.name', 'Sales Team'));
        $attachments   = $this->attachments($email);

        try {
            // The owner's SMTP from Settings > Mail Settings (falls back to .env)
            $mailConfig->applySmtp();

            // Tracking: the token keys the open pixel, the Message-ID lets replies be matched.
            $trackingToken = Str::random(40);
            $fromAddress   = (string) $mailConfig->fromAddress();
            $messageId     = $trackingToken . '@' . (Str::after($fromAddress, '@') ?: 'localhost');

            Mail::to($email->to_email)->send(new OutreachMail(
                null, $email->body, $email->subject, $senderName, $senderCompany,
                emailAttachments: $attachments, trackingToken: $trackingToken, messageId: $messageId,
            ));

            $email->forceFill([
                'status'         => LeadEmail::STATUS_SENT,
                'sent_at'        => now(),
                'tracking_token' => $trackingToken,
                'message_id'     => $messageId,
            ])->save();

            $this->updateLead($email, Lead::STATUS_SENT);
        } catch (\Throwable $e) {
            $email->forceFill(['status' => LeadEmail::STATUS_FAILED, 'error' => $e->getMessage(), 'sent_at' => now()])->save();

            Log::warning('SendBulkEmailJob: send failed', ['lead_email_id' => $email->id, 'error' => $e->getMessage()]);

            $this->updateLead($email, Lead::STATUS_FAILED);
            $this->notifyWhenFinished($email, $bulk);

            return;
        }

        // The email is out; a Sent-folder copy that fails must not mark it failed.
        try {
            // Rendered WITHOUT the pixel, otherwise opening the Sent folder would count as an open.
            $sentCopy = new OutreachMail(null, $email->body, $email->subject, $senderName, $senderCompany, emailAttachments: $attachments);

            $imap->copyToSentFolder(
                to:          (string) $email->to_email,
                subject:     $email->subject,
                htmlBody:    $sentCopy->render(),
                fromName:    $senderName,
                fromEmail:   $fromAddress,
                attachments: $attachments,
            );
        } catch (\Throwable $e) {
            Log::warning('SendBulkEmailJob: Sent-folder copy failed', ['lead_email_id' => $email->id, 'error' => $e->getMessage()]);
        }

        $this->notifyWhenFinished($email, $bulk);
    }

    /**
     * Not passed through job middleware, so it sets the owner itself. Only an
     * error outside handle()'s own try lands here - leave the email failed,
     * not stuck on "sending".
     */
    public function failed(\Throwable $exception): void
    {
        $this->asOwner(fn () => LeadEmail::whereKey($this->emailId)
            ->where('status', LeadEmail::STATUS_SENDING)
            ->update(['status' => LeadEmail::STATUS_FAILED, 'error' => $exception->getMessage()]));
    }

    /**
     * An email to a lead (Leads > Bulk Email) moves the lead on, as sending
     * from the compose modal does: Sent once it is out, Failed when it was
     * still New. A reply already on record is never undone.
     */
    private function updateLead(LeadEmail $email, string $status): void
    {
        if (! $email->lead_id) {
            return;
        }

        $from = $status === Lead::STATUS_SENT
            ? [Lead::STATUS_NEW, Lead::STATUS_FAILED, Lead::STATUS_SENT]
            : [Lead::STATUS_NEW, Lead::STATUS_FAILED];

        Lead::whereKey($email->lead_id)->whereIn('status', $from)->update(['status' => $status]);
    }

    /**
     * The template attachments chosen for the send, as files on disk. One
     * deleted since is left out rather than failing the whole email.
     *
     * @return array<int, array{path: string, name: string}>
     */
    private function attachments(LeadEmail $email): array
    {
        $files = [];

        foreach ($email->attachments ?? [] as $attachment) {
            $path = storage_path('app/' . ($attachment['path'] ?? ''));

            if (! empty($attachment['path']) && is_file($path)) {
                $files[] = ['path' => $path, 'name' => $attachment['name'] ?? basename($path)];
            }
        }

        return $files;
    }

    /** The last email of a bulk send raises one notification with the totals. */
    private function notifyWhenFinished(LeadEmail $email, EmailBulkService $bulk): void
    {
        if (! $email->batch_id) {
            return;
        }

        $progress = $bulk->progress($email->batch_id);

        if ($progress['running']) {
            return;
        }

        AppNotification::raise(
            $progress['failed'] > 0 ? 'info' : 'success',
            'Email bulk send finished',
            "{$progress['sent']} sent, {$progress['failed']} failed"
                . ($progress['cancelled'] ? ", {$progress['cancelled']} cancelled" : '') . '.',
            route('email-activity.index', ['batch' => $email->batch_id], false),
        );
    }
}
