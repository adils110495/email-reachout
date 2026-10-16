<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSetting;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Each user's own WhatsApp Business Cloud API connection. Always mine() - a
 * super admin manages their own number here too, never another user's token.
 */
class WhatsappSettingController extends Controller
{
    public function index(): View
    {
        $setting = $this->ownSetting();

        return view('whatsapp-settings.index', [
            'setting'    => $setting,
            'webhookUrl' => $setting->callbackUrl(),
            'apiVersion' => config('services.whatsapp.api_version'),
        ]);
    }

    /**
     * The webhook card: the public URL the callback URL is built on, and
     * optionally a verify token of the user's own choosing.
     */
    public function updateWebhook(Request $request): RedirectResponse
    {
        $setting = $this->ownSetting();

        // The callback path adds ~60 characters, and Meta takes at most 200.
        $data = $request->validate([
            'public_url'   => ['nullable', 'url', 'starts_with:https://', 'max:140'],
            'verify_token' => ['nullable', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9._~-]+$/'],
        ], [
            'public_url.starts_with' => 'Meta only calls https addresses - use your live domain, or an ngrok / cloudflared tunnel.',
            'public_url.url'         => 'Enter a full address, e.g. https://app.example.com',
            'verify_token.regex'     => 'Use letters, numbers and . _ ~ - only.',
        ]);

        $setting->public_url = isset($data['public_url']) ? rtrim($data['public_url'], '/') : null;

        // A blank verify token keeps the current one.
        if (filled($data['verify_token'] ?? null)) {
            $setting->forceFill(['verify_token' => $data['verify_token']]);
        }

        $setting->save();

        return redirect()->route('whatsapp-settings.index')->with('success',
            'Webhook settings saved. If Meta already had the old URL or token, press "Set in Meta" again.');
    }

    /**
     * Hand the callback URL and verify token to Meta for this Business Account
     * (webhook override), instead of pasting them in the App Dashboard.
     */
    public function registerWebhook(WhatsappService $whatsapp): JsonResponse
    {
        $setting = WhatsappSetting::forActingUser();

        if (! $setting || blank($setting->business_account_id) || blank($setting->getRawOriginal('access_token'))) {
            return response()->json(['ok' => false, 'message' => 'Save the WhatsApp Business Account ID and the access token first.']);
        }

        if (blank($setting->getRawOriginal('app_secret'))) {
            return response()->json(['ok' => false, 'message' => 'Add the App Secret first - without it every webhook call is refused.']);
        }

        $url = $setting->callbackUrl();

        if (! str_starts_with($url, 'https://')) {
            return response()->json(['ok' => false, 'message' => 'Meta only accepts an https URL. Enter your public URL above (live domain, or an ngrok / cloudflared tunnel), save, and try again.']);
        }

        if (strlen($url) > 200) {
            return response()->json(['ok' => false, 'message' => 'The callback URL is longer than the 200 characters Meta allows - use a shorter public URL.']);
        }

        try {
            $whatsapp->overrideWebhook($setting, $url, $setting->verify_token);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'message' => 'Done - Meta now sends this account\'s webhooks to this app.']);
    }

    /** Where Meta sends this Business Account's webhooks right now. */
    public function webhookStatus(WhatsappService $whatsapp): JsonResponse
    {
        $setting = WhatsappSetting::forActingUser();

        if (! $setting || blank($setting->business_account_id) || blank($setting->getRawOriginal('access_token'))) {
            return response()->json(['ok' => false, 'message' => 'Save the WhatsApp Business Account ID and the access token first.']);
        }

        try {
            $overrides = $whatsapp->webhookOverrides($setting);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }

        if (in_array($setting->callbackUrl(), $overrides, true)) {
            return response()->json(['ok' => true, 'message' => 'Connected - Meta sends this account\'s webhooks to this app.']);
        }

        return response()->json(['ok' => false, 'message' => $overrides
            ? 'Meta sends this account\'s webhooks to another URL: ' . implode(', ', $overrides) . '. Press "Set in Meta" to point them here.'
            : 'No URL set for this account yet - Meta uses the app\'s default callback URL from the App Dashboard. Press "Set in Meta", or paste the URL there.']);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_country_code' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'phone_number_id'      => ['nullable', 'regex:/^\d{5,30}$/'],
            'business_account_id'  => ['nullable', 'regex:/^\d{5,30}$/'],
            'access_token'         => ['nullable', 'string', 'max:2000'],
            'app_secret'           => ['nullable', 'string', 'max:255'],
        ], [
            'default_country_code.regex' => 'Enter just the country code digits, e.g. 91.',
            'phone_number_id.regex'      => 'The Phone Number ID is a number - copy it from Meta > WhatsApp > API Setup.',
            'business_account_id.regex'  => 'The WhatsApp Business Account ID is a number - copy it from Meta > WhatsApp > API Setup.',
        ]);

        $setting = WhatsappSetting::mine()->firstOrNew();
        $active  = $request->boolean('is_active');

        // Blank secrets keep the stored ones - the form never shows them.
        foreach (['access_token', 'app_secret'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $hasToken = filled($data['access_token'] ?? null) || filled($setting->getRawOriginal('access_token'));

        if ($active && (blank($data['phone_number_id'] ?? null) || ! $hasToken)) {
            return back()->withInput()->withErrors([
                'phone_number_id' => 'To send through the Cloud API, fill in the Phone Number ID and the Access Token - '
                    . 'or switch "Active" off to use only "Open in WhatsApp".',
            ]);
        }

        $data['default_country_code'] = ltrim((string) ($data['default_country_code'] ?? ''), '+') ?: null;

        $setting->fill($data + ['is_active' => $active])->save();

        // The account (or its token) may have changed - drop its cached templates.
        Cache::forget(WhatsappService::templateCacheKey($setting));

        return redirect()->route('whatsapp-settings.index')->with('success', 'WhatsApp settings saved.');
    }

    /**
     * Try the Phone Number ID and token in the form (the saved token when the
     * field is blank) without saving anything.
     */
    public function test(Request $request, WhatsappService $whatsapp): JsonResponse
    {
        $request->validate([
            'phone_number_id' => ['required', 'regex:/^\d{5,30}$/'],
        ], [
            'phone_number_id.required' => 'Enter the Phone Number ID first.',
            'phone_number_id.regex'    => 'The Phone Number ID is a number.',
        ]);

        try {
            $token = $request->filled('access_token')
                ? (string) $request->input('access_token')
                : (string) WhatsappSetting::forActingUser()?->access_token;

            if ($token === '') {
                return response()->json(['ok' => false, 'message' => 'Enter the access token first.']);
            }

            $info = $whatsapp->phoneNumberInfo((string) $request->input('phone_number_id'), $token);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }

        $number  = $info['display_phone_number'] ?? 'unknown number';
        $name    = $info['verified_name'] ?? null;
        $quality = $info['quality_rating'] ?? null;

        return response()->json([
            'ok'      => true,
            'message' => 'Connected: ' . $number . ($name ? " ({$name})" : '') . ($quality ? " - quality {$quality}" : '') . '.',
        ]);
    }

    /**
     * The user's row, created on first visit. Meta's setup asks for the
     * callback URL before the credentials are all to hand, so the webhook key
     * and verify token must exist before anything has been saved. A row with
     * no token sends nothing (canSend() is false).
     */
    private function ownSetting(): WhatsappSetting
    {
        // createOrFirst: two tabs opened at once cannot both insert (user_id is unique).
        return WhatsappSetting::forActingUser() ?? WhatsappSetting::mine()->createOrFirst();
    }
}
