<?php

namespace App\Http\Controllers;

use App\Models\WaAccount;
use App\Models\WhatsappSetting;
use App\Services\WhatsappService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Meta's webhooks: delivery / read / failed statuses for messages sent from
 * here, incoming messages (replies) and - for a coexistence number - messages
 * sent from the phone app, chat history and contacts (WhatsappService::applyChange).
 *
 * Two endpoints, both public (Meta has no session):
 *
 *   /webhooks/whatsapp/{key}   one user's number, set up by hand. The random
 *                              key picks the user; signed with that user's App
 *                              Secret (or the platform app's).
 *   /webhooks/meta/whatsapp    the platform Meta app's callback URL (App
 *                              Dashboard) - every number connected through
 *                              Embedded Signup. The phone number id picks the
 *                              owner; signed with META_APP_SECRET.
 *
 * No valid signature, no processing - nobody can fake a "read" or a reply.
 * (Not /webhooks/whatsapp: the laravel-whatsapp package already owns it.)
 */
class WhatsappWebhookController extends Controller
{
    /**
     * The one-time check Meta makes when the callback URL is saved.
     * PHP turns "hub.mode" in a query string into "hub_mode".
     */
    public function verify(Request $request, string $key): Response
    {
        return $this->challenge($request, $this->settingFor($key)->verify_token);
    }

    public function receive(Request $request, string $key, WhatsappService $whatsapp): Response
    {
        $setting = $this->settingFor($key);

        abort_unless($this->signedWithAny($request, [$this->ownSecret($setting), $this->appSecret()]), 403);

        try {
            $this->access()->runAs($setting->user_id, fn () => $whatsapp->applyWebhook($request->json()->all(), $setting));
        } catch (\Throwable $e) {
            // Meta retries anything but a 200 for days - a bug here must not
            // turn into a retry storm. It is logged instead.
            Log::error('WhatsApp webhook: could not apply', ['user_id' => $setting->user_id, 'error' => $e->getMessage()]);
        }

        return response('', 200);
    }

    /** GET /webhooks/meta/whatsapp - the App Dashboard's verify request, WA_WEBHOOK_VERIFY_TOKEN. */
    public function verifyApp(Request $request): Response
    {
        return $this->challenge($request, (string) config('services.whatsapp.webhook_verify_token'));
    }

    /**
     * POST /webhooks/meta/whatsapp - one delivery can carry changes for several
     * numbers (and owners). Each change is applied as its number's owner;
     * history and contact syncs are queued, so this answers fast.
     */
    public function receiveApp(Request $request, WhatsappService $whatsapp): Response
    {
        abort_unless($this->signedWithAny($request, [$this->appSecret()]), 403);

        foreach ($request->json('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $field = (string) ($change['field'] ?? 'messages');

                try {
                    $ownerId = $this->ownerOf((string) ($value['metadata']['phone_number_id'] ?? ''), (string) ($entry['id'] ?? ''));

                    if (! $ownerId) {
                        Log::info('WhatsApp app webhook: no account for this number', ['field' => $field, 'entry' => $entry['id'] ?? null]);
                        continue;
                    }

                    $this->access()->runAs($ownerId, function () use ($whatsapp, $field, $value) {
                        $setting = WhatsappSetting::forActingUser() ?? WhatsappSetting::mine()->createOrFirst();
                        $whatsapp->applyChange($field, $value, $setting);
                    });
                } catch (\Throwable $e) {
                    Log::error('WhatsApp app webhook: could not apply', ['field' => $field, 'error' => $e->getMessage()]);
                }
            }
        }

        return response('', 200);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function challenge(Request $request, string $expected): Response
    {
        $token = (string) $request->query('hub_verify_token', '');

        abort_unless($request->query('hub_mode') === 'subscribe' && $expected !== '' && hash_equals($expected, $token), 403);

        return response((string) $request->query('hub_challenge', ''), 200, ['Content-Type' => 'text/plain']);
    }

    /** @param  array<int, string>  $secrets */
    private function signedWithAny(Request $request, array $secrets): bool
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        foreach (array_filter($secrets) as $secret) {
            if (WhatsappService::validSignature($payload, $signature, $secret)) {
                return true;
            }
        }

        return false;
    }

    private function appSecret(): string
    {
        return (string) config('services.whatsapp.meta_app_secret');
    }

    /** The user's own App Secret - empty when unset or unreadable here (another APP_KEY). */
    private function ownSecret(WhatsappSetting $setting): string
    {
        try {
            return (string) $setting->app_secret;
        } catch (DecryptException) {
            return '';
        }
    }

    /**
     * Whose number a change is for: an Embedded Signup account by phone number
     * id (or by WhatsApp Business Account id), else a number typed into
     * Settings > WhatsApp. No session here, so the lookup is system-wide.
     */
    private function ownerOf(string $phoneNumberId, string $wabaId): ?int
    {
        return $this->access()->asSystem(function () use ($phoneNumberId, $wabaId) {
            $account = $phoneNumberId !== ''
                ? WaAccount::where('phone_number_id', $phoneNumberId)->first()
                : ($wabaId !== '' ? WaAccount::where('waba_id', $wabaId)->latest('id')->first() : null);

            $ownerId = $account?->user_id
                ?? ($phoneNumberId !== '' ? WhatsappSetting::where('phone_number_id', $phoneNumberId)->value('user_id') : null);

            return $ownerId ? (int) $ownerId : null;
        });
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
