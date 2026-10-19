<?php

namespace App\Http\Controllers;

use App\Models\SerpapiSetting;
use App\Services\SerpApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Each user's own SerpAPI key - used by Find Leads, the GMB search and the AI
 * writer's company research. Always mine(): a super admin manages their own
 * key here too, never another user's.
 */
class SerpapiSettingController extends Controller
{
    public function index(SerpApiConfigService $serp): View
    {
        return view('serpapi-settings.index', [
            'setting'   => SerpapiSetting::forActingUser(),
            'source'    => $serp->source(),
            'hasEnvKey' => filled(config('services.serpapi.key')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'api_key'  => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9]+$/'],
            'country'  => ['nullable', 'string', 'size:2', 'alpha'],
            'language' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z]{2,3}(-[A-Za-z]{2,4})?$/'],
        ], [
            'api_key.regex'  => 'A SerpAPI key is letters and numbers only - copy it again from serpapi.com.',
            'country.size'   => 'Use the two-letter Google country code, e.g. in, us, uk.',
            'language.regex' => 'Use a Google language code, e.g. en, hi, pt-br.',
        ]);

        $setting = SerpapiSetting::mine()->firstOrNew();

        $setting->fill([
            'country'   => filled($data['country'] ?? null) ? strtolower($data['country']) : null,
            'language'  => filled($data['language'] ?? null) ? strtolower($data['language']) : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        // A blank key field keeps the saved key; "remove" drops it.
        if ($request->boolean('remove_key')) {
            $setting->api_key = null;
        } elseif (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        }

        $setting->save();

        return redirect()->route('serpapi-settings.index')->with('success', 'SerpAPI settings saved.');
    }

    /**
     * Check a key against SerpAPI's Account API (uses no search credit): the
     * one typed in the form, else the saved one, else the server's.
     */
    public function test(Request $request, SerpApiConfigService $serp): JsonResponse
    {
        $key = trim((string) $request->input('api_key'))
            ?: (SerpapiSetting::forActingUser()?->api_key ?: (string) config('services.serpapi.key'));

        if ($key === '') {
            return response()->json(['ok' => false, 'message' => 'Enter an API key first.']);
        }

        try {
            $account = $serp->account($key);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }

        // "Free Plan" stays "Free Plan", "Developer" becomes "Developer plan".
        $plan = $account['plan'] && ! preg_match('/plan$/i', $account['plan']) ? "{$account['plan']} plan" : $account['plan'];

        $parts = array_filter([
            $plan,
            $account['left'] !== null ? number_format($account['left']) . ' searches left' : null,
            $account['used'] !== null ? number_format($account['used']) . ' used this month' : null,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Connected' . ($parts ? ' - ' . implode(', ', $parts) : '') . '.',
            'account' => $account,
        ]);
    }
}
