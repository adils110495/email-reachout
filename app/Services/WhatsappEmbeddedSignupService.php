<?php

namespace App\Services;

use App\Models\WaAccount;
use App\Models\WhatsappSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Meta's Embedded Signup, server side: what happens after the user finished
 * the popup on Settings > WhatsApp (the browser posts the code and the ids).
 *
 *   a) exchange the code for a business token
 *      (and check, with debug_token, which WhatsApp Business Accounts it was granted)
 *   b) read the phone number
 *   c) subscribe the app to the WhatsApp Business Account (webhooks)
 *   d) coexistence (FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING): ask for the app's
 *      contacts and chat history - within 24 hours, and never /register;
 *      a new number (FINISH): /register it with a 6-digit PIN
 *   e) save the wa_accounts row (token encrypted)
 *
 * Every Graph error is logged with its full error body - never a token, a
 * code or the app secret (only the path is logged, not the query).
 */
class WhatsappEmbeddedSignupService
{
    public const EVENT_COEXISTENCE = 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING';
    public const EVENT_FINISH      = 'FINISH';
    public const EVENT_ONLY_WABA   = 'FINISH_ONLY_WABA';
    public const EVENT_UNKNOWN     = 'UNKNOWN';   // the popup's message never arrived

    public const EVENTS = [self::EVENT_COEXISTENCE, self::EVENT_FINISH, self::EVENT_ONLY_WABA, self::EVENT_UNKNOWN];

    /** Everything the button needs: app, config and secret all set. */
    public function configured(): bool
    {
        return filled(config('services.whatsapp.meta_app_id'))
            && filled(config('services.whatsapp.meta_app_secret'))
            && filled(config('services.whatsapp.es_config_id'));
    }

    /**
     * @return array{account: WaAccount, pin: ?string, warnings: array<int, string>}
     *
     * @throws RuntimeException a step failed - the message says which, in plain words
     */
    public function onboard(string $code, ?string $wabaId, ?string $phoneNumberId, ?string $businessId, string $event): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Embedded Signup is not set up on this server: META_APP_ID, META_APP_SECRET and META_ES_CONFIG_ID are needed in .env.');
        }

        // a) The code is good for a few minutes and only once.
        $grant = $this->call('get', 'oauth/access_token', [
            'client_id'     => config('services.whatsapp.meta_app_id'),
            'client_secret' => config('services.whatsapp.meta_app_secret'),
            'code'          => $code,
        ], null, 'exchange the signup code for a token');

        $token = (string) ($grant['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Meta did not return an access token for the signup code.');
        }

        // The browser said which account; the token says which accounts were
        // really shared. Never trust the posted id on its own.
        $granted = $this->grantedWabaIds($token);

        if ($wabaId !== null && $granted !== [] && ! in_array($wabaId, $granted, true)) {
            throw new RuntimeException('That WhatsApp Business Account was not shared in the signup. Please connect again.');
        }

        $wabaId ??= $granted[0] ?? throw new RuntimeException('Meta did not say which WhatsApp Business Account was connected. Please connect again.');

        if ($event === self::EVENT_ONLY_WABA && $phoneNumberId === null) {
            throw new RuntimeException('The WhatsApp Business Account was connected, but no phone number was added to it. Connect again and choose a number.');
        }

        $phoneNumberId ??= $this->onlyPhoneNumber($wabaId, $token);

        // b) The number - this also proves the token can reach it.
        $phone = $this->call('get', $phoneNumberId, ['fields' => 'display_phone_number,verified_name'], $token, 'read the phone number');

        // Connected by someone else already? One number, one owner.
        $existing = app(UserAccessService::class)->asSystem(fn () => WaAccount::where('phone_number_id', $phoneNumberId)->first());
        $actorId  = (int) app(UserAccessService::class)->id();

        if ($existing && (int) $existing->user_id !== $actorId) {
            throw new RuntimeException('This number is already connected by another user of this app.');
        }

        // c) Webhooks for this account come to the app (its callback URL in the App Dashboard).
        $this->call('post', $wabaId . '/subscribed_apps', [], $token, 'subscribe the app to the WhatsApp Business Account');

        $coexistence = $event === self::EVENT_COEXISTENCE;
        $warnings    = [];
        $pin         = null;
        $synced      = ['contacts' => null, 'history' => null];

        // d) Coexistence: the number stays on the phone - /register would take it
        // away. Ask for its contacts, then its chat history (Meta: within 24h).
        if ($coexistence) {
            [$synced, $warnings] = $this->requestSync($phoneNumberId, $token);
        } elseif ($event === self::EVENT_FINISH) {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $this->call('post', $phoneNumberId . '/register', ['messaging_product' => 'whatsapp', 'pin' => $pin], $token, 'register the phone number');
        } else {
            $warnings[] = 'Meta did not say how the number was connected, so it was neither registered nor synced. If it is your WhatsApp Business app number, use "Request sync" below.';
        }

        // e) Save - the owner is the signed-in user.
        $account = $existing ?? new WaAccount();
        $account->fill([
            'waba_id'                    => $wabaId,
            'phone_number_id'            => $phoneNumberId,
            'display_phone_number'       => $phone['display_phone_number'] ?? null,
            'verified_name'              => $phone['verified_name'] ?? null,
            'business_id'                => $businessId,
            'access_token'               => $token,
            'token_type'                 => $grant['token_type'] ?? null,
            'token_expires_at'           => isset($grant['expires_in']) ? now()->addSeconds((int) $grant['expires_in']) : null,
            'is_coexistence'             => $coexistence,
            'onboarding_event'           => $event,
            'contacts_sync_requested_at' => $synced['contacts'],
            'history_sync_requested_at'  => $synced['history'],
            'history_status'             => $synced['history'] ? WaAccount::HISTORY_REQUESTED : null,
            'onboarded_at'               => now(),
            'status'                     => WaAccount::STATUS_CONNECTED,
            'last_error'                 => $warnings ? implode(' ', $warnings) : null,
        ]);
        if ($pin !== null) {
            $account->two_step_pin = $pin;
        }
        $account->save();

        // Messages go out from this number now: make sure the user's settings
        // row exists and is switched on, and forget the old number's templates.
        $setting = WhatsappSetting::forActingUser() ?? WhatsappSetting::mine()->createOrFirst();
        if (! $setting->is_active) {
            $setting->forceFill(['is_active' => true])->save();
        }
        Cache::forget(WhatsappService::templateCacheKey($setting));

        return ['account' => $account, 'pin' => $pin, 'warnings' => $warnings];
    }

    /**
     * Ask a coexistence number for its app data again (the button on the
     * settings page) - when the first request failed. Meta only allows it in
     * the first 24 hours after onboarding.
     *
     * @return array<int, string> warnings
     */
    public function resync(WaAccount $account): array
    {
        $token = $account->readableToken() ?? throw new RuntimeException('The saved token cannot be read on this server - connect the number again.');

        [$synced, $warnings] = $this->requestSync((string) $account->phone_number_id, $token);

        $account->forceFill([
            // Meta takes smb_app_data only from a coexistence number
            'is_coexistence'             => $account->is_coexistence || $synced['contacts'] || $synced['history'],
            'contacts_sync_requested_at' => $synced['contacts'] ?? $account->contacts_sync_requested_at,
            'history_sync_requested_at'  => $synced['history'] ?? $account->history_sync_requested_at,
            'history_status'             => $synced['history'] ? WaAccount::HISTORY_REQUESTED : $account->history_status,
            'last_error'                 => $warnings ? implode(' ', $warnings) : null,
        ])->save();

        return $warnings;
    }

    // ── Steps ──────────────────────────────────────────────────────────────

    /**
     * POST /{phone}/smb_app_data, contacts first, then history. A failed one is
     * a warning, not a failed signup: the number is connected either way.
     *
     * @return array{0: array{contacts: ?\Illuminate\Support\Carbon, history: ?\Illuminate\Support\Carbon}, 1: array<int, string>}
     */
    private function requestSync(string $phoneNumberId, string $token): array
    {
        $synced   = ['contacts' => null, 'history' => null];
        $warnings = [];

        foreach (['contacts' => 'smb_app_state_sync', 'history' => 'history'] as $key => $syncType) {
            try {
                $this->call('post', $phoneNumberId . '/smb_app_data', ['messaging_product' => 'whatsapp', 'sync_type' => $syncType], $token, "request the {$key} sync");
                $synced[$key] = now();
            } catch (RuntimeException $e) {
                $warnings[] = ucfirst($key) . ' sync was not started: ' . $e->getMessage();
            }
        }

        return [$synced, $warnings];
    }

    /**
     * The WhatsApp Business Accounts the token was granted, from debug_token's
     * granular scopes.
     *
     * @return array<int, string>
     */
    private function grantedWabaIds(string $token): array
    {
        $debug = $this->call('get', 'debug_token', [
            'input_token'  => $token,
            'access_token' => config('services.whatsapp.meta_app_id') . '|' . config('services.whatsapp.meta_app_secret'),
        ], null, 'check the token');

        return collect($debug['data']['granular_scopes'] ?? [])
            ->whereIn('scope', ['whatsapp_business_management', 'whatsapp_business_messaging'])
            ->flatMap(fn (array $scope) => $scope['target_ids'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** When the popup did not say which number: the account's only one. */
    private function onlyPhoneNumber(string $wabaId, string $token): string
    {
        $numbers = $this->call('get', $wabaId . '/phone_numbers', ['fields' => 'id,display_phone_number'], $token, 'list the phone numbers')['data'] ?? [];

        if (count($numbers) !== 1) {
            throw new RuntimeException(count($numbers) === 0
                ? 'The WhatsApp Business Account has no phone number yet.'
                : 'The WhatsApp Business Account has several numbers and Meta did not say which one was connected. Please connect again.');
        }

        return (string) $numbers[0]['id'];
    }

    // ── HTTP ───────────────────────────────────────────────────────────────

    /**
     * One Graph API call. Logs a failure with its full error body - and only
     * the path, since the query can hold the code, the secret or a token.
     */
    private function call(string $method, string $path, array $data, ?string $token, string $step): array
    {
        $url = rtrim((string) config('services.whatsapp.graph_url'), '/')
            . '/' . config('services.whatsapp.api_version') . '/' . ltrim($path, '/');

        $request = $this->http($token);

        try {
            // A POST with no fields (subscribed_apps) goes without a body, as Meta's own examples do.
            /** @var Response $response */
            $response = match (true) {
                $method === 'get' => $request->get($url, $data),
                $data === []      => $request->send('POST', $url),
                default           => $request->post($url, $data),
            };
        } catch (\Throwable $e) {
            Log::error('WhatsApp embedded signup: request failed', ['step' => $step, 'path' => $path, 'error' => $e->getMessage()]);
            throw new RuntimeException("Could not {$step}: Meta did not answer.");
        }

        if ($response->failed()) {
            Log::error('WhatsApp embedded signup: Graph API error', [
                'step'   => $step,
                'path'   => $path,
                'status' => $response->status(),
                'error'  => $response->json() ?? $response->body(),
            ]);

            $error = $response->json('error');
            $text  = is_array($error) ? (string) ($error['error_user_msg'] ?? $error['message'] ?? 'unknown error') : 'HTTP ' . $response->status();

            throw new RuntimeException("Could not {$step}: {$text}");
        }

        return $response->json() ?? [];
    }

    private function http(?string $token): PendingRequest
    {
        $request = Http::acceptJson()->timeout((int) config('services.whatsapp.timeout', 20));

        return $token ? $request->withToken($token) : $request;
    }
}
