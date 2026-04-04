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
    public function find(string $keyword, int $maxResults = 10): array
    {
        Log::info('LeadFinderService: searching', ['keyword' => $keyword]);

        $apiKey = config('services.serpapi.key');

        if (empty($apiKey)) {
            Log::error('LeadFinderService: SERPAPI_KEY is not set in .env');
            return [];
        }

        try {
            $response = Http::timeout(20)
                ->get('https://serpapi.com/search', [
                    'engine'      => 'google',
                    'q'           => $keyword . ' contact email',
                    'api_key'     => $apiKey,
                    'num'         => $maxResults + 5, // fetch a few extra to cover filtered ones
                    'hl'          => 'en',
                    'gl'          => 'us',
                    'output'      => 'json',
                ]);

            if (! $response->successful()) {
                Log::error('LeadFinderService: SerpAPI HTTP ' . $response->status(), [
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();

            // SerpAPI returns organic results in the "organic_results" key
            $organicResults = $data['organic_results'] ?? [];

            if (empty($organicResults)) {
                Log::warning('LeadFinderService: SerpAPI returned no organic_results', [
                    'keyword'  => $keyword,
                    'response' => array_keys($data),
                ]);
                return [];
            }

            $leads = [];

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

                if (count($leads) >= $maxResults) {
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
