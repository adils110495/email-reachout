<?php

namespace App\Services;

use App\Models\SerpapiSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Which SerpAPI key and search defaults to use, for the acting user.
 *
 * The key saved under Settings > SerpAPI wins; the server's SERPAPI_KEY
 * (.env) is only the fallback, so an install where nobody saved a key keeps
 * working. Searches run in jobs as their owner (RunsAsOwner), so a background
 * search spends its owner's credits.
 *
 * Not a singleton on purpose: a long-lived queue worker serves many users.
 */
class SerpApiConfigService
{
    public const SOURCE_SETTINGS = 'settings';
    public const SOURCE_ENV      = 'env';

    public function key(): ?string
    {
        return $this->saved()?->activeKey() ?: (config('services.serpapi.key') ?: null);
    }

    /** Where key() comes from - settings | env | null (no key at all). */
    public function source(): ?string
    {
        if ($this->saved()?->activeKey()) {
            return self::SOURCE_SETTINGS;
        }

        return config('services.serpapi.key') ? self::SOURCE_ENV : null;
    }

    /**
     * Google country ("gl") for Find Leads: the saved one, else LEAD_COUNTRY.
     * Call it in the request - env() is unreliable in a worker once config is cached.
     */
    public function country(): ?string
    {
        $saved = $this->saved();

        return ($saved?->is_active ? $saved->country : null) ?: (env('LEAD_COUNTRY') ?: null);
    }

    /** Google language ("hl") for Find Leads: the saved one, else LEAD_LANGUAGE. */
    public function language(): ?string
    {
        $saved = $this->saved();

        return ($saved?->is_active ? $saved->language : null) ?: (env('LEAD_LANGUAGE') ?: null);
    }

    /**
     * The account behind a key, from SerpAPI's Account API - free, it uses no
     * search credit.
     *
     * @return array{email: ?string, plan: ?string, per_month: ?int, left: ?int, used: ?int}
     *
     * @throws RuntimeException with SerpAPI's own reason (invalid key, …)
     */
    public function account(string $key): array
    {
        $response = Http::timeout(15)->acceptJson()->get('https://serpapi.com/account.json', ['api_key' => $key]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error') ?: 'SerpAPI answered HTTP ' . $response->status() . '.');
        }

        $int = fn (string $field) => is_numeric($response->json($field)) ? (int) $response->json($field) : null;

        return [
            'email'     => $response->json('account_email'),
            'plan'      => $response->json('plan_name'),
            'per_month' => $int('searches_per_month'),
            'left'      => $int('total_searches_left') ?? $int('plan_searches_left'),
            'used'      => $int('this_month_usage'),
        ];
    }

    private function saved(): ?SerpapiSetting
    {
        // Until the migration has run, behave as before: the .env key only.
        return Schema::hasTable('serpapi_settings') ? SerpapiSetting::forActingUser() : null;
    }
}
