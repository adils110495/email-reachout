<?php

namespace App\Services;

use App\Jobs\SendWhatsappMessageJob;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bulk WhatsApp: one approved template to many contacts.
 *
 * Every contact gets its own WhatsappMessage row (status "queued") and its own
 * delayed SendWhatsappMessageJob, spaced $interval seconds apart. A burst of
 * identical messages to cold contacts is what gets a number reported and its
 * quality rating cut, so the queue paces them instead of firing all at once.
 */
class WhatsappBulkService
{
    /** Seconds the user may put between two messages of a bulk send. */
    public const INTERVALS = [2, 5, 10, 30, 60];

    public const DEFAULT_INTERVAL = 5;

    /** Written in a template value, replaced by each contact's own name: {{name}}, or the older {name}. */
    public const NAME_TOKEN = '/\{\{\s*name\s*\}\}|\{name\}/i';

    public function __construct(private readonly WhatsappService $whatsapp) {}

    /**
     * Queue the template for every contact. Runs as the contacts' owner: their
     * WhatsApp number sends it and the messages land in their log.
     *
     * @param  Collection<int, array{name: ?string, phone: ?string, gmb_lead_id?: ?int, cash_lead_id?: ?int}>  $contacts
     * @param  array{name: string, language: string, header: ?string, body: ?string, params: array<int|string, string>}  $template
     * @return array{batch_id: string, queued: int, no_number: int, duplicate: int, already_messaged: int}
     *
     * @throws RuntimeException when the Cloud API is not connected
     */
    public function queue(Collection $contacts, array $template, int $interval, bool $skipContacted): array
    {
        $setting = WhatsappSetting::forActingUser();

        if (! $setting?->canSend()) {
            throw new RuntimeException('Bulk sending goes through the WhatsApp Cloud API, which is not connected for this account. Set it up under Settings > WhatsApp.');
        }

        $countryCode = $this->whatsapp->countryCode($setting);
        $summary     = ['batch_id' => (string) Str::uuid(), 'queued' => 0, 'no_number' => 0, 'duplicate' => 0, 'already_messaged' => 0];
        $recipients  = [];

        foreach ($contacts as $contact) {
            $phone = $this->whatsapp->normalizePhone($contact['phone'] ?? null, $countryCode);

            if (! $phone) {
                $summary['no_number']++;
            } elseif (isset($recipients[$phone])) {
                $summary['duplicate']++;   // two leads of the same business share a number
            } else {
                $recipients[$phone] = array_merge($contact, ['phone' => $phone]);
            }
        }

        if ($skipContacted && $recipients !== []) {
            // PHP turns the digit-only keys into ints; compare as strings so
            // MySQL can use the (user_id, phone) index.
            $contacted = WhatsappMessage::whereIn('phone', array_map('strval', array_keys($recipients)))
                ->where('direction', WhatsappMessage::DIRECTION_OUT)
                ->whereNotIn('status', WhatsappMessage::NOT_DELIVERED)
                ->distinct()
                ->pluck('phone');

            foreach ($contacted as $phone) {
                unset($recipients[$phone]);
                $summary['already_messaged']++;
            }
        }

        foreach (array_values($recipients) as $i => $contact) {
            $params = $this->personalise($template['params'], (string) ($contact['name'] ?? ''));

            $message = WhatsappMessage::create([
                'batch_id'          => $summary['batch_id'],
                'gmb_lead_id'       => $contact['gmb_lead_id'] ?? null,
                'cash_lead_id'      => $contact['cash_lead_id'] ?? null,
                'contact_name'      => $contact['name'] ?? null,
                'phone'             => $contact['phone'],
                'channel'           => WhatsappMessage::CHANNEL_API,
                'type'              => WhatsappMessage::TYPE_TEMPLATE,
                'template_name'     => $template['name'],
                'template_language' => $template['language'],
                'params'            => $params,
                'body'              => $this->render($template, $params),
                'status'            => WhatsappMessage::STATUS_QUEUED,
            ]);

            SendWhatsappMessageJob::dispatch($message->id, $message->user_id)
                ->onQueue('default')
                ->delay(now()->addSeconds($i * $interval));

            $summary['queued']++;
        }

        return $summary;
    }

    /**
     * Stop a bulk send: messages still waiting are cancelled, and their jobs
     * skip them when their turn comes. One already being sent is not stopped.
     */
    public function cancel(string $batchId): int
    {
        return WhatsappMessage::where('batch_id', $batchId)
            ->where('status', WhatsappMessage::STATUS_QUEUED)
            ->update(['status' => WhatsappMessage::STATUS_CANCELLED]);
    }

    /**
     * Where a bulk send stands - for its progress panel and the poller.
     *
     * @return array{total: int, pending: int, accepted: int, delivered: int, read: int, replied: int, failed: int, cancelled: int, done: int, progress: int, running: bool}
     */
    public function progress(string $batchId): array
    {
        $byStatus = WhatsappMessage::where('batch_id', $batchId)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n);

        $count = fn (string ...$statuses) => collect($statuses)->sum(fn (string $s) => $byStatus[$s] ?? 0);

        $total   = $byStatus->sum();
        $pending = $count(...WhatsappMessage::PENDING);

        return [
            'total'     => $total,
            'pending'   => $pending,
            // Accepted by WhatsApp: everything that got past "sending" without failing.
            'accepted'  => $count(WhatsappMessage::STATUS_SENT, WhatsappMessage::STATUS_DELIVERED, WhatsappMessage::STATUS_READ),
            'delivered' => $count(WhatsappMessage::STATUS_DELIVERED, WhatsappMessage::STATUS_READ),
            'read'      => $count(WhatsappMessage::STATUS_READ),
            'replied'   => WhatsappMessage::where('batch_id', $batchId)->whereNotNull('replied_at')->count(),
            'failed'    => $count(WhatsappMessage::STATUS_FAILED),
            'cancelled' => $count(WhatsappMessage::STATUS_CANCELLED),
            'done'      => $total - $pending,
            'progress'  => $total > 0 ? (int) floor(($total - $pending) * 100 / $total) : 100,
            'running'   => $pending > 0,
        ];
    }

    /**
     * The template values for one contact: {{name}} becomes their name.
     *
     * @param  array<int|string, string>  $params
     * @return array<int|string, string>
     */
    private function personalise(array $params, string $name): array
    {
        // A callback, so a "$1" in a business's name is not read as a back-reference.
        return array_map(fn (string $value) => trim(preg_replace_callback(self::NAME_TOKEN, fn () => $name, $value)), $params);
    }

    /** The message as the contact reads it - kept in the log. */
    private function render(array $template, array $params): ?string
    {
        $fill = fn (?string $text) => preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/',
            fn (array $m) => $params[$m[1]] ?? $m[0],
            (string) $text,
        );

        $text = trim(($template['header'] ? $fill($template['header']) . "\n\n" : '') . $fill($template['body']));

        return $text !== '' ? $text : null;
    }
}
