<?php

namespace App\Http\Controllers;

use App\Models\WaAccount;
use App\Services\WhatsappEmbeddedSignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * "Connect WhatsApp Business App" on Settings > WhatsApp: Meta's Embedded
 * Signup with coexistence. The popup runs in the browser; this finishes it
 * (WhatsappEmbeddedSignupService) and manages the connected numbers.
 *
 * Always the signed-in user's own numbers (owner scope; mine() for a super
 * admin too) - a number connected here sends that user's messages.
 */
class WhatsappEmbeddedSignupController extends Controller
{
    /** POST /api/wa/embedded-signup - the popup's code and ids. */
    public function store(Request $request, WhatsappEmbeddedSignupService $signup): JsonResponse
    {
        $data = $request->validate([
            'code'            => ['required', 'string', 'max:2000'],
            'waba_id'         => ['nullable', 'regex:/^\d{5,32}$/'],
            'phone_number_id' => ['nullable', 'regex:/^\d{5,32}$/'],
            'business_id'     => ['nullable', 'regex:/^\d{5,32}$/'],
            'event'           => ['nullable', Rule::in(WhatsappEmbeddedSignupService::EVENTS)],
        ]);

        try {
            $result = $signup->onboard(
                $data['code'],
                $data['waba_id'] ?? null,
                $data['phone_number_id'] ?? null,
                $data['business_id'] ?? null,
                $data['event'] ?? WhatsappEmbeddedSignupService::EVENT_UNKNOWN,
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('WhatsApp embedded signup: failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'The number could not be connected - see the server log.'], 500);
        }

        $account = $result['account'];
        $number  = $account->display_phone_number ?: $account->phone_number_id;

        return response()->json([
            'ok'          => true,
            'message'     => $account->is_coexistence
                ? "Connected {$number}. It keeps working in the WhatsApp Business app, and contacts and chat history are being synced."
                : "Connected {$number}.",
            'coexistence' => $account->is_coexistence,
            // Shown once: the two-step verification PIN a new number was registered with.
            'pin'         => $result['pin'],
            'warnings'    => $result['warnings'],
        ]);
    }

    /** POST /api/wa/accounts/{id}/sync - ask a coexistence number for its contacts and history again. */
    public function resync(int $id, WhatsappEmbeddedSignupService $signup): JsonResponse
    {
        $account = WaAccount::mine()->findOrFail($id);

        if (! $account->canRequestSync()) {
            return response()->json(['ok' => false, 'message' => 'Only a WhatsApp Business app (coexistence) number has app data to sync.'], 422);
        }

        try {
            $warnings = $signup->resync($account);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json($warnings
            ? ['ok' => false, 'message' => implode(' ', $warnings)]
            : ['ok' => true, 'message' => 'Sync requested - contacts and chat history arrive over the next minutes.']);
    }

    /**
     * DELETE /api/wa/accounts/{id} - stop sending from this number here. It
     * stays connected in Meta (and on the phone); sending falls back to the
     * Phone Number ID and token typed into the form, if any.
     */
    public function disconnect(int $id): JsonResponse
    {
        WaAccount::mine()->findOrFail($id)->forceFill(['status' => WaAccount::STATUS_DISCONNECTED])->save();

        return response()->json(['ok' => true, 'message' => 'Disconnected. Messages no longer go out from this number.']);
    }
}
