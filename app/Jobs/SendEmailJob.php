<?php

namespace App\Jobs;

use App\Mail\OutreachMail;
use App\Models\Lead;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before the job is marked as failed.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying after a failure.
     */
    public int $backoff = 60;

    /**
     * Maximum seconds the job may run.
     */
    public int $timeout = 120;

    public function __construct(
        public readonly Lead   $lead,
        public readonly string $subject = '',
        public readonly string $body    = '',
    ) {}

    /**
     * Execute the queued job.
     * Uses pre-written subject/body when provided; falls back to AI generation.
     */
    public function handle(AIService $aiService): void
    {
        if ($this->lead->status === Lead::STATUS_SENT) {
            Log::info('SendEmailJob: Skipping — already sent', ['lead_id' => $this->lead->id]);
            return;
        }

        $senderName    = env('SENDER_NAME', 'Sales Team');
        $senderCompany = env('SENDER_COMPANY', 'Our Company');

        try {
            // Use the compose-modal content if provided, otherwise generate via AI
            $emailBody   = $this->body    ?: $aiService->generateOutreachEmail($this->lead, $senderName, $senderCompany);
            $subjectLine = $this->subject ?: $aiService->generateSubjectLine($this->lead, $senderCompany);

            // Send the email
            Mail::to($this->lead->email)
                ->send(new OutreachMail($this->lead, $emailBody, $subjectLine, $senderName, $senderCompany));

            // Mark as sent
            $this->lead->update(['status' => Lead::STATUS_SENT]);

            Log::info('SendEmailJob: Email sent successfully', [
                'lead_id' => $this->lead->id,
                'email'   => $this->lead->email,
            ]);

        } catch (\Exception $e) {
            Log::error('SendEmailJob: Failed to send email', [
                'lead_id' => $this->lead->id,
                'email'   => $this->lead->email,
                'error'   => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Mark as failed only on the final attempt
            if ($this->attempts() >= $this->tries) {
                $this->lead->update(['status' => Lead::STATUS_FAILED]);
            }

            // Re-throw so the queue knows this job failed and should be retried
            throw $e;
        }
    }

    /**
     * Called when all retry attempts are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $this->lead->update(['status' => Lead::STATUS_FAILED]);

        Log::error('SendEmailJob: Job permanently failed', [
            'lead_id' => $this->lead->id,
            'error'   => $exception->getMessage(),
        ]);
    }
}
