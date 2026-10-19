<?php

namespace App\Services;

use App\Jobs\SendBulkEmailJob;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Support\EmailPlaceholders;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bulk email: one subject and body to many contacts.
 *
 * The email twin of WhatsappBulkService. Every contact gets its own LeadEmail
 * row (status "queued") and its own delayed SendBulkEmailJob, spaced $interval
 * seconds apart - a burst of identical cold emails from one mailbox is what
 * gets it marked as spam.
 */
class EmailBulkService
{
    /** Seconds the user may put between two emails of a bulk send. */
    public const INTERVALS = [10, 30, 60, 120];

    public const DEFAULT_INTERVAL = 30;

    public function __construct(private readonly MailConfigService $mailConfig) {}

    /**
     * Checked before anything of a bulk send is queued, so a missing mailbox
     * cannot leave a WhatsApp + email send half queued.
     *
     * @throws RuntimeException when the acting user has no mailbox to send from
     */
    public function ensureReady(): void
    {
        if (! $this->mailConfig->fromAddress()) {
            throw new RuntimeException('Bulk email needs a mailbox to send from, and none is set up for this account. Add your SMTP under Settings > Mail Settings.');
        }

        // Otherwise every email of the send would fail on its turn, one by one.
        if ($problem = $this->mailConfig->smtpProblem()) {
            throw new RuntimeException($problem);
        }
    }

    /**
     * Queue the email for every contact. Runs as the contacts' owner: their
     * SMTP sends it and the emails land in their Email Activity.
     *
     * @param  Collection<int, array{name: ?string, email: ?string, lead_id?: ?int, cash_lead_id?: ?int}>  $contacts
     * @param  array{subject: string, body: string, attachments: array<int, array{name: string, path: string, size?: int}>}  $email
     *         body is safe HTML (EmailHtml::render); {{placeholders}} are filled per contact
     * @return array{batch_id: string, queued: int, no_email: int, duplicate: int, already_emailed: int}
     *
     * @throws RuntimeException when there is no mailbox to send from
     */
    public function queue(Collection $contacts, array $email, int $interval, bool $skipContacted): array
    {
        $this->ensureReady();

        $summary    = ['batch_id' => (string) Str::uuid(), 'queued' => 0, 'no_email' => 0, 'duplicate' => 0, 'already_emailed' => 0];
        $recipients = [];

        foreach ($contacts as $contact) {
            $address = strtolower(trim((string) ($contact['email'] ?? '')));

            if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $summary['no_email']++;
            } elseif (isset($recipients[$address])) {
                $summary['duplicate']++;   // two deals of the same business share an address
            } else {
                $recipients[$address] = array_merge($contact, ['email' => $address]);
            }
        }

        if ($skipContacted && $recipients !== []) {
            foreach ($this->alreadyEmailed(array_keys($recipients)) as $address) {
                if (isset($recipients[$address])) {
                    unset($recipients[$address]);
                    $summary['already_emailed']++;
                }
            }
        }

        // Your name and company, read here in the request (see EmailPlaceholders::values()).
        $sender = EmailPlaceholders::values(null);

        foreach (array_values($recipients) as $i => $contact) {
            $values = ['client_name' => trim((string) ($contact['name'] ?? ''))] + $sender;

            $row = LeadEmail::create([
                'batch_id'     => $summary['batch_id'],
                'lead_id'      => $contact['lead_id'] ?? null,
                'cash_lead_id' => $contact['cash_lead_id'] ?? null,
                'contact_name' => $contact['name'] ?? null,
                'to_email'     => $contact['email'],
                'subject'      => mb_substr(EmailPlaceholders::fill($email['subject'], $values), 0, 255),
                'body'         => EmailPlaceholders::fill($email['body'], $values, html: true),
                'attachments'  => $email['attachments'] ?: null,
                'status'       => LeadEmail::STATUS_QUEUED,
            ]);

            SendBulkEmailJob::dispatch($row->id, $row->user_id)
                ->onQueue('emails')
                ->delay(now()->addSeconds($i * $interval));

            $summary['queued']++;
        }

        return $summary;
    }

    /**
     * Stop a bulk send: emails still waiting are cancelled, and their jobs
     * skip them when their turn comes. One already being sent is not stopped.
     */
    public function cancel(string $batchId): int
    {
        return LeadEmail::where('batch_id', $batchId)
            ->where('status', LeadEmail::STATUS_QUEUED)
            ->update(['status' => LeadEmail::STATUS_CANCELLED]);
    }

    /**
     * Where a bulk send stands - for its progress panel and the poller.
     *
     * @return array{total: int, pending: int, sent: int, opened: int, replied: int, failed: int, cancelled: int, done: int, progress: int, running: bool}
     */
    public function progress(string $batchId): array
    {
        $byStatus = LeadEmail::where('batch_id', $batchId)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n);

        $total   = $byStatus->sum();
        $pending = collect(LeadEmail::PENDING)->sum(fn (string $s) => $byStatus[$s] ?? 0);

        return [
            'total'     => $total,
            'pending'   => $pending,
            'sent'      => $byStatus[LeadEmail::STATUS_SENT] ?? 0,
            'opened'    => LeadEmail::where('batch_id', $batchId)->where('open_count', '>', 0)->count(),
            'replied'   => LeadEmail::where('batch_id', $batchId)->whereNotNull('replied_at')->count(),
            'failed'    => $byStatus[LeadEmail::STATUS_FAILED] ?? 0,
            'cancelled' => $byStatus[LeadEmail::STATUS_CANCELLED] ?? 0,
            'done'      => $total - $pending,
            'progress'  => $total > 0 ? (int) floor(($total - $pending) * 100 / $total) : 100,
            'running'   => $pending > 0,
        ];
    }

    /**
     * Which of these addresses the acting user has emailed before. A bulk
     * email records its address; one sent from the Leads compose modal is
     * known by its lead, which it marked sent.
     *
     * @param  array<int, string>  $addresses  lower-cased
     * @return array<int, string>
     */
    private function alreadyEmailed(array $addresses): array
    {
        $bulk = LeadEmail::whereIn('to_email', $addresses)
            ->whereNotIn('status', LeadEmail::NOT_DELIVERED)
            ->distinct()
            ->pluck('to_email');

        $leads = Lead::whereIn('email', $addresses)
            ->whereIn('status', [Lead::STATUS_SENT, Lead::STATUS_REPLIED])
            ->pluck('email');

        return $bulk->merge($leads)->map(fn (string $a) => strtolower($a))->unique()->values()->all();
    }
}
