<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\WaAccount;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Coexistence webhooks - a number connected through Embedded Signup that also
 * stays on the WhatsApp Business app:
 *
 *   smb_message_echoes  a message sent from the app on the phone
 *   history             up to ~6 months of past chats, in chunks
 *   smb_app_state_sync  the app's contacts added / changed / removed
 *
 * Everything is idempotent - Meta repeats webhooks: messages are keyed by
 * their wamid (unique), contacts by (user, phone). Runs as the number's owner.
 */
class WhatsappSyncService
{
    /** Not chats: group / call / status items have no place in a 1:1 message log. */
    private const IGNORED_TYPES = ['reaction', 'unsupported', 'system', 'ephemeral', 'call', 'status', 'request_welcome'];

    /** Meta's code for "history sharing is turned off" on the phone. */
    private const HISTORY_DECLINED = 2593109;

    public function __construct(private readonly WhatsappService $whatsapp) {}

    /** Messages sent from the WhatsApp Business app: outgoing rows in the same log. */
    public function applyEchoes(array $value, WhatsappSetting $setting): void
    {
        $business = $this->businessNumber($value, $setting);
        $rows     = [];

        foreach ($value['message_echoes'] ?? [] as $echo) {
            $to = $this->digits($echo['to'] ?? '');

            if ($row = $this->messageRow($echo, $to, $business, (int) $setting->user_id, forceOut: true)) {
                $rows[] = $row;
            }
        }

        $this->insert($rows);
    }

    /**
     * Past chats. Each chunk carries threads (one per contact) of messages, and
     * a progress percentage; a chunk with errors means the phone declined.
     */
    public function applyHistory(array $value, WhatsappSetting $setting): void
    {
        $business = $this->businessNumber($value, $setting);
        $account  = $setting->account();

        foreach ($value['history'] ?? [] as $chunk) {
            if (! empty($chunk['errors'])) {
                $this->historyDeclined($chunk['errors'], $account);
                continue;
            }

            foreach ($chunk['threads'] ?? [] as $thread) {
                $contact = $this->digits($thread['id'] ?? '');
                $rows    = [];

                foreach ($thread['messages'] ?? [] as $message) {
                    if ($row = $this->messageRow($message, $contact, $business, (int) $setting->user_id)) {
                        $rows[] = $row;
                    }
                }

                $this->insert($rows);

                if ($contact !== '') {
                    $this->upsertContacts([$contact => null], WhatsappContact::SOURCE_HISTORY, (int) $setting->user_id, keepNames: true);
                }
            }

            $this->historyProgress((int) ($chunk['metadata']['progress'] ?? 0), $account);
        }
    }

    /** The app's address book: add / update upserts the contact, remove marks it removed. */
    public function applyStateSync(array $value, WhatsappSetting $setting): void
    {
        $upserts = [];

        foreach ($value['state_sync'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'contact') {
                continue;
            }

            $contact = $item['contact'] ?? [];
            $phone   = $this->digits($contact['phone_number'] ?? '');

            if ($phone === '') {
                continue;
            }

            if (($item['action'] ?? 'add') === 'remove') {
                WhatsappContact::where('phone', $phone)->update(['removed_at' => now()]);
                unset($upserts[$phone]);
                continue;
            }

            $upserts[$phone] = trim((string) ($contact['full_name'] ?? $contact['first_name'] ?? '')) ?: null;
        }

        $this->upsertContacts($upserts, WhatsappContact::SOURCE_APP, (int) $setting->user_id, keepNames: false);
    }

    // ── Rows ───────────────────────────────────────────────────────────────

    /**
     * One whatsapp_messages row, or null for what is not a 1:1 chat message.
     * Direction: sent by the business number = out, else in.
     */
    private function messageRow(array $message, string $contact, string $business, int $userId, bool $forceOut = false): ?array
    {
        $type  = (string) ($message['type'] ?? 'text');
        $wamid = (string) ($message['id'] ?? '');

        if ($wamid === '' || in_array($type, self::IGNORED_TYPES, true)) {
            return null;
        }

        $from = $this->digits($message['from'] ?? '');
        $out  = $forceOut || ($business !== '' ? $from === $business : ($contact !== '' && $from !== $contact));
        $phone = $out ? ($this->digits($message['to'] ?? '') ?: $contact) : ($from ?: $contact);

        if ($phone === '' || strlen($phone) > 20) {
            return null;
        }

        $at     = $this->timestamp($message['timestamp'] ?? null);
        $status = $out ? $this->historyStatus($message['history_context']['status'] ?? null) : WhatsappMessage::STATUS_RECEIVED;

        return [
            'user_id'      => $userId,
            'phone'        => $phone,
            'contact_name' => null,
            'channel'      => WhatsappMessage::CHANNEL_PHONE,
            'direction'    => $out ? WhatsappMessage::DIRECTION_OUT : WhatsappMessage::DIRECTION_IN,
            'type'         => $type === 'template' ? WhatsappMessage::TYPE_TEMPLATE : WhatsappMessage::TYPE_TEXT,
            'body'         => Str::limit($this->whatsapp->incomingText($message), 4000),
            'wamid'        => $wamid,
            'status'       => $status,
            'sent_at'      => $at,
            'delivered_at' => in_array($status, [WhatsappMessage::STATUS_DELIVERED, WhatsappMessage::STATUS_READ], true) ? $at : null,
            'read_at'      => $status === WhatsappMessage::STATUS_READ ? $at : null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
    }

    /**
     * insertOrIgnore: a wamid already stored (a repeated webhook, a message
     * already in the log) is skipped. Names come from the synced contacts.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $names = WhatsappContact::whereIn('phone', array_unique(array_column($rows, 'phone')))
            ->whereNotNull('name')->pluck('name', 'phone');

        foreach ($rows as &$row) {
            $row['contact_name'] = $names[$row['phone']] ?? null;
        }
        unset($row);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('whatsapp_messages')->insertOrIgnore($chunk);
        }
    }

    /**
     * @param  array<string, ?string>  $contacts  phone => name
     * @param  bool  $keepNames  history knows no names: never blank out one the app synced
     */
    private function upsertContacts(array $contacts, string $source, int $userId, bool $keepNames): void
    {
        if ($contacts === []) {
            return;
        }

        $now  = now();
        $rows = [];

        foreach ($contacts as $phone => $name) {
            $rows[] = ['user_id' => $userId, 'phone' => (string) $phone, 'name' => $name, 'source' => $source,
                       'removed_at' => null, 'created_at' => $now, 'updated_at' => $now];
        }

        // History only adds contacts that are not there yet; the app sync owns names.
        $update = $keepNames ? ['updated_at'] : ['name', 'source', 'removed_at', 'updated_at'];

        DB::table('whatsapp_contacts')->upsert($rows, ['user_id', 'phone'], $update);
    }

    // ── History status ─────────────────────────────────────────────────────

    private function historyDeclined(array $errors, ?WaAccount $account): void
    {
        $declined = collect($errors)->contains(fn (array $e) => (int) ($e['code'] ?? 0) === self::HISTORY_DECLINED);

        Log::info('WhatsApp history: not shared', ['errors' => $errors]);

        if (! $account || $account->history_status === WaAccount::HISTORY_DECLINED) {
            return;   // already said so - webhooks repeat
        }

        $account->forceFill(['history_status' => WaAccount::HISTORY_DECLINED])->save();

        AppNotification::raise('info', 'WhatsApp chat history not shared',
            $declined
                ? 'History sharing was turned off on the phone, so past chats were not imported. New messages still sync.'
                : 'Meta could not share the past chats: ' . Str::limit((string) ($errors[0]['title'] ?? $errors[0]['message'] ?? 'unknown error'), 160),
            route('whatsapp-settings.index', [], false));
    }

    private function historyProgress(int $progress, ?WaAccount $account): void
    {
        if (! $account || $account->history_status === WaAccount::HISTORY_COMPLETE) {
            return;
        }

        if ($progress >= 100) {
            $account->forceFill(['history_status' => WaAccount::HISTORY_COMPLETE])->save();

            AppNotification::raise('success', 'WhatsApp chat history imported',
                'Past chats of ' . ($account->display_phone_number ?: 'your number') . ' are in the WhatsApp log.',
                route('whatsapp.index', [], false));
        } elseif ($account->history_status !== WaAccount::HISTORY_IMPORTING) {
            $account->forceFill(['history_status' => WaAccount::HISTORY_IMPORTING])->save();
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** The business's own number, to tell sent from received. */
    private function businessNumber(array $value, WhatsappSetting $setting): string
    {
        return $this->digits($value['metadata']['display_phone_number'] ?? $setting->account()?->display_phone_number ?? '');
    }

    /** History knows how far a sent message got. */
    private function historyStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'READ', 'PLAYED'  => WhatsappMessage::STATUS_READ,
            'DELIVERED'       => WhatsappMessage::STATUS_DELIVERED,
            'ERROR', 'FAILED' => WhatsappMessage::STATUS_FAILED,
            default           => WhatsappMessage::STATUS_SENT,
        };
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    private function timestamp(mixed $unix): Carbon
    {
        // As WhatsappService does: in the app's timezone, like every other timestamp in the log.
        return is_numeric($unix) ? now()->setTimestamp((int) $unix) : now();
    }
}
