<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSetting;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Meta's webhook for one user's WhatsApp number: delivery / read / failed
 * statuses for messages sent from here, and incoming messages (replies).
 *
 * Public - Meta has no session. The random key in the URL picks the user, and
 * every POST must carry a valid signature made with that user's App Secret,
 * so nobody else can fake a "read" or a reply.
 */
class WhatsappWebhookController extends Controller
{
    /**
     * The one-time check Meta makes when the callback URL is saved.
     * PHP turns "hub.mode" in a query string into "hub_mode".
     */
    public function verify(Request $request, string $key): Response
    {
        $setting = $this->settingFor($key);

        $token = (string) $request->query('hub_verify_token', '');

        abort_unless(
            $request->query('hub_mode') === 'subscribe' && hash_equals($setting->verify_token, $token),
            403,
        );

        return response((string) $request->query('hub_challenge', ''), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request, string $key, WhatsappService $whatsapp): Response
    {
        $setting = $this->settingFor($key);
        $secret  = (string) $setting->app_secret;

        abort_unless(
            $secret !== '' && WhatsappService::validSignature($request->getContent(), $request->header('X-Hub-Signature-256'), $secret),
            403,
        );

        try {
            $this->access()->runAs($setting->user_id, fn () => $whatsapp->applyWebhook($request->json()->all(), $setting));
        } catch (\Throwable $e) {
            // Meta retries anything but a 200 for days - a bug here must not
            // turn into a retry storm. It is logged instead.
            Log::error('WhatsApp webhook: could not apply', ['user_id' => $setting->user_id, 'error' => $e->getMessage()]);
        }

        return response('', 200);
    }

    /** No session here, so the lookup is system-wide; the 40-char key is what identifies the user. */
    private function settingFor(string $key): WhatsappSetting
    {
        $setting = strlen($key) === 40
            ? $this->access()->asSystem(fn () => WhatsappSetting::where('webhook_key', $key)->first())
            : null;

        abort_unless($setting, 404);

        return $setting;
    }
}
