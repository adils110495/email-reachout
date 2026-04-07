<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeadFinderService
{
    /**
     * Find leads for a keyword using SerpAPI (serpapi.com).
     *
     * SerpAPI proxies Google through residential IPs — never blocked from Docker.
     * Free plan: 100 searches/month. Paid plans start at $50/mo.
     * Sign up at: https://serpapi.com
     *
     * @return array<int, array{url: string, title: string}>
     */
    public function find(string $keyword, ?string $country = null, ?string $language = null): array
    {
        $limit = 25;

        // Resolve country/language: input → env → omit (global)
        $country  = $country  ?: (env('LEAD_COUNTRY',  '') ?: null);
        $language = $language ?: (env('LEAD_LANGUAGE', '') ?: null);

        Log::info('LeadFinderService: searching', [
            'keyword'  => $keyword,
            'limit'    => $limit,
            'country'  => $country  ?? 'global',
            'language' => $language ?? 'any',
        ]);

        $apiKey = config('services.serpapi.key');

        if (empty($apiKey)) {
            Log::error('LeadFinderService: SERPAPI_KEY is not set in .env');
            return [];
        }

        try {
            $baseParams = [
                'engine'  => 'google',
                'q'       => $keyword . ' contact email',
                'api_key' => $apiKey,
                'num'     => 10, // SerpAPI returns 10 per page
                'output'  => 'json',
            ];

            // Only add gl/hl if explicitly configured — omitting means global
            if ($country)  $baseParams['gl'] = strtolower($country);
            if ($language) $baseParams['hl'] = strtolower($language);

            $leads = [];
            $start = 0;

            // Paginate through SerpAPI pages until we have 25 leads
            while (count($leads) < $limit) {
                $params = array_merge($baseParams, ['start' => $start]);

                $response = Http::timeout(20)
                    ->get('https://serpapi.com/search', $params);

                if (! $response->successful()) {
                    Log::error('LeadFinderService: SerpAPI HTTP ' . $response->status(), [
                        'body' => $response->body(),
                    ]);
                    break;
                }

                $data           = $response->json();
                $organicResults = $data['organic_results'] ?? [];

                if (empty($organicResults)) {
                    Log::warning('LeadFinderService: SerpAPI returned no organic_results', [
                        'keyword' => $keyword,
                        'start'   => $start,
                    ]);
                    break;
                }

                foreach ($organicResults as $result) {
                    $url   = trim($result['link'] ?? '');
                    $title = trim($result['title'] ?? '');

                    if (empty($url) || ! str_starts_with($url, 'http')) {
                        continue;
                    }

                    if ($this->isSkippable($url)) {
                        continue;
                    }

                    $leads[] = [
                        'url'   => rtrim($url, '/'),
                        'title' => $title ?: (parse_url($url, PHP_URL_HOST) ?? $url),
                    ];

                    if (count($leads) >= $limit) {
                        break;
                    }
                }

                $start += 10;

                // SerpAPI free plan max ~100 results; stop if no more pages
                if (count($organicResults) < 10) {
                    break;
                }
            }

            Log::info('LeadFinderService: done', ['found' => count($leads)]);

            return $leads;

        } catch (\Throwable $e) {
            Log::error('LeadFinderService: SerpAPI request failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Returns true for URLs that should never be treated as leads.
     */
    private function isSkippable(string $url): bool
    {
        static $skip = [
            'google.', 'googleapis.', 'googleusercontent.',
            'bing.com', 'yahoo.com', 'duckduckgo.com',
            'youtube.com', 'youtu.be',
            'facebook.com', 'twitter.com', 'x.com',
            'instagram.com', 'tiktok.com', 'pinterest.com',
            'linkedin.com', 'wikipedia.org', 'wikimedia.',
            'amazon.com', 'ebay.com', 'reddit.com',
            'quora.com', 'medium.com', 'yelp.com',
            'tripadvisor.com', 'trustpilot.com',
            'apple.com', 'microsoft.com', 'cloudflare.com',
        ];

        foreach ($skip as $pattern) {
            if (str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
