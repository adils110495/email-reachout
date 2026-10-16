<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * WhatsApp Business Cloud API (Meta's official API), plus the wa.me
 * click-to-chat link for users who have not connected it.
 *
 * Meta's rule that shapes everything here: a business may only START a
 * conversation with a template Meta has approved. Free text is delivered only
 * inside the 24 hours after the contact last wrote to you.
 */
class WhatsappService
{
    /** E.164 allows at most 15 digits; fewer than 8 is not a real number. */
    private const MIN_DIGITS = 8;
    private const MAX_DIGITS = 15;

    /** Seconds the approved-template list is reused for. */
    private const TEMPLATE_CACHE_TTL = 600;

    /** Plain-language hints for the errors people actually run into. */
    private const ERROR_HINTS = [
        190    => 'The access token is invalid or has expired - generate a permanent System User token.',
        131030 => 'The number is not in your test recipient list. Add it in Meta > WhatsApp > API Setup, or use a live business number.',
        131047 => 'More than 24 hours since this contact last wrote to you - free text is not allowed. Send an approved template instead.',
        131026 => 'The message could not be delivered. The number may not be on WhatsApp.',
        132000 => 'The number of template variables does not match the template.',
        132001 => 'This template does not exist in that language, or is not approved yet.',
        131056 => 'Too many messages to this number in a short time. Wait a little and try again.',
        2200   => 'Meta could not verify the callback URL. It must be public https and reachable from the internet.',
        2201   => 'Meta reached the callback URL but the check failed - the verify token did not match.',
    ];

    // ── Numbers ────────────────────────────────────────────────────────────

    /** The user's default country code, falling back to the .env one. */
    public function countryCode(?WhatsappSetting $setting): ?string
    {
        return $setting?->default_country_code ?: config('services.whatsapp.default_country_code');
    }

    /**
     * A phone number as WhatsApp wants it: international digits, no "+"
     * ("+91 98765-43210" -> "919876543210"). Null when it cannot be one.
     *
     * A number written without a country code gets $countryCode in front, its
     * national trunk "0" dropped (UK 07911 123456 -> 447911123456).
     */
    public function normalizePhone(?string $raw, ?string $countryCode): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $international = str_starts_with($raw, '+');
        $digits        = preg_replace('/\D+/', '', $raw);

        if (! $international && str_starts_with($digits, '00')) {
            $international = true;
            $digits        = substr($digits, 2);
        }

        if (! $international) {
            $cc = preg_replace('/\D+/', '', (string) $countryCode);

            $alreadyInternational = $digits !== '' && $digits[0] !== '0' && strlen($digits) >= 11
                && ($cc === '' || str_starts_with($digits, $cc));

            if ($alreadyInternational) {
                // Saved with its country code, just without the "+": 919876543210.
            } elseif ($cc !== '') {
                $digits = $cc . ltrim($digits, '0');
            } else {
                return null; // a local number, and no country to put in front of it
            }
        }

        $length = strlen($digits);

        return $length >= self::MIN_DIGITS && $length <= self::MAX_DIGITS && $digits[0] !== '0' ? $digits : null;
    }

    /** Click-to-chat: opens WhatsApp (app or web) on this number with $text typed in. */
    public function chatLink(string $phone, string $text = ''): string
    {
        return 'https://wa.me/' . $phone . ($text !== '' ? '?text=' . rawurlencode($text) : '');
    }

    // ── Sending ────────────────────────────────────────────────────────────

    /** @return string Meta's message id (wamid) */
    public function sendText(WhatsappSetting $setting, string $to, string $body): string
    {
        return $this->send($setting, [
            'to'   => $to,
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => $body],
        ]);
    }

    /**
     * @param  array<int|string, string>  $params  body variable => value: [1 => 'Acme'] for
     *                                             {{1}}, or ['name' => 'Acme'] for {{name}}
     * @return string Meta's message id (wamid)
     */
    public function sendTemplate(WhatsappSetting $setting, string $to, string $name, string $language, array $params): string
    {
        $template = ['name' => $name, 'language' => ['code' => $language]];

        if ($params !== []) {
            // Positional templates ({{1}}, {{2}}) take the values in number
            // order; named ones ({{name}}) say which variable each value is for.
            $positional = collect(array_keys($params))->every(fn ($key) => ctype_digit((string) $key));

            if ($positional) {
                ksort($params, SORT_NUMERIC);
            }

            $template['components'] = [[
                'type'       => 'body',
                'parameters' => collect($params)->map(fn (string $value, $key) => ['type' => 'text', 'text' => $value]
                    + ($positional ? [] : ['parameter_name' => (string) $key]))->values()->all(),
            ]];
        }

        return $this->send($setting, ['to' => $to, 'type' => 'template', 'template' => $template]);
    }

    private function send(WhatsappSetting $setting, array $message): string
    {
        $response = $this->graph((string) $setting->access_token)->post(
            $this->url($setting->phone_number_id . '/messages'),
            ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual'] + $message,
        );

        $this->throwIfFailed($response);

        return (string) ($response->json('messages.0.id')
            ?? throw new RuntimeException('WhatsApp accepted the request but returned no message id.'));
    }

    // ── Account ────────────────────────────────────────────────────────────

    /**
     * The account's APPROVED templates, simplified for the compose modal.
     * Cached briefly: Meta rate-limits the endpoint and templates rarely change.
     *
     * @return array<int, array{name: string, language: string, category: ?string, header: ?string, body: string, params: array<int, string>, unsupported: ?string}>
     */
    public function templates(WhatsappSetting $setting, bool $fresh = false): array
    {
        $this->businessAccountId($setting, 'to load templates');

        $key = self::templateCacheKey($setting);

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::TEMPLATE_CACHE_TTL, function () use ($setting) {
            $response = $this->graph((string) $setting->access_token)->get(
                $this->url($setting->business_account_id . '/message_templates'),
                ['fields' => 'name,language,status,category,components', 'limit' => 200],
            );

            $this->throwIfFailed($response);

            return collect($response->json('data', []))
                ->filter(fn (array $t) => ($t['status'] ?? '') === 'APPROVED')
                ->map(fn (array $t) => $this->describeTemplate($t))
                ->sortBy('name')
                ->values()
                ->all();
        });
    }

    public static function templateCacheKey(WhatsappSetting $setting): string
    {
        return 'whatsapp-templates:' . $setting->id;
    }

    /**
     * The number behind a Phone Number ID - used by "Test Connection".
     *
     * @return array{display_phone_number?: string, verified_name?: string, quality_rating?: string}
     */
    public function phoneNumberInfo(string $phoneNumberId, string $token): array
    {
        $response = $this->graph($token)->get(
            $this->url($phoneNumberId),
            ['fields' => 'display_phone_number,verified_name,quality_rating'],
        );

        $this->throwIfFailed($response);

        return $response->json();
    }

    // ── Webhook ────────────────────────────────────────────────────────────

    /**
     * Point the Business Account's webhooks at $callbackUrl (Meta's "webhook
     * override") - the same as pasting it under WhatsApp > Configuration, but
     * for this account only, so several users can share one Meta app.
     *
     * Meta calls the URL with $verifyToken before accepting it, so it must be
     * public https and answering already.
     */
    public function overrideWebhook(WhatsappSetting $setting, string $callbackUrl, string $verifyToken): void
    {
        $waba = $this->businessAccountId($setting, 'to set the webhook');

        $response = $this->graph((string) $setting->access_token)->post(
            $this->url($waba . '/subscribed_apps'),
            ['override_callback_uri' => $callbackUrl, 'verify_token' => $verifyToken],
        );

        $this->throwIfFailed($response);

        if ($response->json('success') !== true) {
            throw new RuntimeException('Meta did not accept the webhook URL.');
        }
    }

    /**
     * The callback URLs Meta currently sends the account's webhooks to, one per
     * subscribed app that has an override. Empty: Meta uses the app's default
     * callback URL from the App Dashboard.
     *
     * @return array<int, string>
     */
    public function webhookOverrides(WhatsappSetting $setting): array
    {
        $waba = $this->businessAccountId($setting, 'to check the webhook');

        $response = $this->graph((string) $setting->access_token)->get($this->url($waba . '/subscribed_apps'));

        $this->throwIfFailed($response);

        return collect($response->json('data', []))
            ->pluck('override_callback_uri')
            ->filter()
            ->values()
            ->all();
    }

    /** Meta signs every webhook POST with the App Secret (X-Hub-Signature-256). */
    public static function validSignature(string $payload, ?string $header, string $secret): bool
    {
        if (! $header || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), substr($header, 7));
    }

    /**
     * Apply one webhook delivery: delivery / read / failed statuses for messages
     * sent from here, and incoming messages as replies. Runs as the setting's
     * owner, so only that user's messages can be touched.
     */
    public function applyWebhook(array $payload, WhatsappSetting $setting): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // One Meta app can serve several numbers - skip the ones that are not this user's.
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                if ($phoneNumberId && $setting->phone_number_id && (string) $phoneNumberId !== $setting->phone_number_id) {
                    continue;
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->applyStatus($status);
                }

                $names = collect($value['contacts'] ?? [])
                    ->mapWithKeys(fn (array $c) => [(string) ($c['wa_id'] ?? '') => $c['profile']['name'] ?? null]);

                foreach ($value['messages'] ?? [] as $incoming) {
                    $this->applyIncoming($incoming, $names[(string) ($incoming['from'] ?? '')] ?? null);
                }
            }
        }
    }

    private function applyStatus(array $status): void
    {
        $wamid = (string) ($status['id'] ?? '');

        // Messages sent from the phone or another tool are not in our log.
        $message = $wamid !== '' ? WhatsappMessage::where('wamid', $wamid)->first() : null;

        if (! $message) {
            return;
        }

        $error = $status['errors'][0] ?? null;

        $message->applyStatus(
            (string) ($status['status'] ?? ''),
            $this->timestamp($status['timestamp'] ?? null),
            $error ? $this->describeError($error) : null,
        );
    }

    /**
     * An incoming message is a reply to the latest message we sent that number.
     * Only the first reply is recorded and notified - a chat is not a feed.
     */
    private function applyIncoming(array $incoming, ?string $profileName): void
    {
        $from = preg_replace('/\D+/', '', (string) ($incoming['from'] ?? ''));

        if ($from === '') {
            return;
        }

        // Only a message that actually went out can have been answered - not one
        // still waiting in a bulk send, nor one that was cancelled.
        $message = WhatsappMessage::where('phone', $from)
            ->whereNotIn('status', [...WhatsappMessage::PENDING, WhatsappMessage::STATUS_CANCELLED])
            ->latest('id')
            ->first();

        if (! $message || $message->replied_at) {
            return;
        }

        $text = $this->incomingText($incoming);

        $message->forceFill([
            'replied_at' => $this->timestamp($incoming['timestamp'] ?? null),
            'reply_body' => Str::limit($text, 1000),
        ])->save();

        AppNotification::raise(
            'success',
            'WhatsApp reply from ' . ($message->contact_name ?: $profileName ?: '+' . $from),
            Str::limit($text, 120),
            route('whatsapp.index', ['activity' => WhatsappMessage::ACTIVITY_REPLIED], false),
        );
    }

    private function incomingText(array $incoming): string
    {
        $type = (string) ($incoming['type'] ?? 'text');

        return match ($type) {
            'text'        => (string) ($incoming['text']['body'] ?? ''),
            'button'      => (string) ($incoming['button']['text'] ?? ''),
            'interactive' => (string) ($incoming['interactive']['button_reply']['title']
                                ?? $incoming['interactive']['list_reply']['title'] ?? '[interactive]'),
            'reaction'    => (string) ($incoming['reaction']['emoji'] ?? '[reaction]'),
            default       => trim('[' . $type . '] ' . ($incoming[$type]['caption'] ?? '')),
        };
    }

    // ── HTTP ───────────────────────────────────────────────────────────────

    /** The WABA id; templates and webhooks live on it, not on the phone number. */
    private function businessAccountId(WhatsappSetting $setting, string $purpose): string
    {
        if (blank($setting->business_account_id)) {
            throw new RuntimeException("Add your WhatsApp Business Account ID under Settings > WhatsApp {$purpose}.");
        }

        return (string) $setting->business_account_id;
    }

    private function graph(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.whatsapp.timeout', 20));
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.whatsapp.graph_url'), '/')
            . '/' . config('services.whatsapp.api_version')
            . '/' . ltrim($path, '/');
    }

    private function throwIfFailed(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $error = $response->json('error');

        throw new RuntimeException(is_array($error)
            ? $this->describeError($error)
            : 'WhatsApp API error (HTTP ' . $response->status() . ').');
    }

    /** One readable line from a Graph API / webhook error object. */
    private function describeError(array $error): string
    {
        $code    = isset($error['code']) ? (int) $error['code'] : null;
        $message = (string) ($error['message'] ?? $error['title'] ?? 'Unknown error');
        $details = (string) ($error['error_data']['details'] ?? '');

        if ($details !== '' && ! str_contains($message, $details)) {
            $message .= ' - ' . $details;
        }

        $hint = $code ? (self::ERROR_HINTS[$code] ?? null) : null;

        return $hint ? $hint . ' (' . $message . ')' : $message;
    }

    /**
     * Only body variables are filled in by the compose modal. A media header, a
     * header variable or a button with its own variable needs input the modal
     * does not ask for, so Meta would reject the send - those are flagged.
     */
    private function describeTemplate(array $template): array
    {
        $components = collect($template['components'] ?? []);
        $header     = $components->firstWhere('type', 'HEADER');
        $buttons    = $components->firstWhere('type', 'BUTTONS')['buttons'] ?? [];
        $body       = (string) ($components->firstWhere('type', 'BODY')['text'] ?? '');

        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $body, $matches);
        $params = array_values(array_unique($matches[1]));

        if ($params !== [] && collect($params)->every(fn (string $p) => ctype_digit($p))) {
            sort($params, SORT_NUMERIC);
        }

        $headerFormat = $header['format'] ?? 'TEXT';

        $unsupported = match (true) {
            $header && $headerFormat !== 'TEXT'               => strtolower($headerFormat) . ' header',
            $header && str_contains($header['text'] ?? '', '{{') => 'header variable',
            collect($buttons)->contains(fn (array $b) => str_contains($b['url'] ?? '', '{{')
                || in_array($b['type'] ?? '', ['COPY_CODE', 'OTP', 'CATALOG', 'MPM'], true)) => 'button variable',
            default => null,
        };

        return [
            'name'        => (string) $template['name'],
            'language'    => (string) ($template['language'] ?? 'en_US'),
            'category'    => $template['category'] ?? null,
            'header'      => $header && $headerFormat === 'TEXT' ? ($header['text'] ?? null) : null,
            'body'        => $body,
            'params'      => $params,
            'unsupported' => $unsupported,
        ];
    }

    private function timestamp(mixed $unix): Carbon
    {
        return is_numeric($unix) ? now()->setTimestamp((int) $unix) : now();
    }
}
