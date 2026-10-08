<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GmbFinderService
{
    /** SerpAPI returns 20 Google Maps results per page. */
    private const PAGE_SIZE = 20;

    /**
     * Search Google Maps (via SerpAPI) and keep only the businesses that have a
     * Google Business Profile but no website.
     *
     * @return array{leads: array<int, array<string, mixed>>, seen: int, with_website: int}
     * @throws \RuntimeException when the key is missing or SerpAPI rejects the request
     */
    public function find(string $keyword, int $maxPages = 3): array
    {
        $apiKey = config('services.serpapi.key');

        if (empty($apiKey)) {
            throw new \RuntimeException('SERPAPI_KEY is not set in .env.');
        }

        $leads       = [];
        $seen        = 0;
        $withWebsite = 0;
        $visited     = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $response = Http::timeout(20)->get('https://serpapi.com/search', [
                'engine'  => 'google_maps',
                'type'    => 'search',
                'q'       => $keyword,
                'start'   => $page * self::PAGE_SIZE,
                'api_key' => $apiKey,
                'output'  => 'json',
            ]);

            if (! $response->successful()) {
                Log::error('GmbFinderService: SerpAPI HTTP ' . $response->status(), ['body' => $response->body()]);

                if ($page === 0) {
                    throw new \RuntimeException('SerpAPI request failed (HTTP ' . $response->status() . ').');
                }
                break;
            }

            $data    = $response->json();
            $results = $data['local_results'] ?? [];

            if ($page === 0 && ! empty($data['error']) && empty($results)) {
                throw new \RuntimeException((string) $data['error']);
            }

            if (empty($results)) {
                break;
            }

            foreach ($results as $result) {
                $placeId = $result['place_id'] ?? null;
                if (! $placeId) {
                    continue;
                }

                // SerpAPI can repeat a place across pages; count and keep it once.
                if (isset($visited[$placeId])) {
                    continue;
                }
                $visited[$placeId] = true;

                $seen++;

                // A listing with any website is not a lead for us.
                if (! empty($result['website']) || ! empty($result['links']['website'])) {
                    $withWebsite++;
                    continue;
                }

                $leads[] = [
                    'place_id' => $placeId,
                    'name'     => (string) ($result['title'] ?? 'Unknown'),
                    'type'     => $result['type'] ?? ($result['types'][0] ?? null),
                    'address'  => $result['address'] ?? null,
                    'phone'    => $result['phone'] ?? null,
                    'rating'   => $result['rating'] ?? null,
                    'reviews'  => $result['reviews'] ?? null,
                    'maps_url' => 'https://www.google.com/maps/place/?q=place_id:' . $placeId,
                ];
            }

            if (count($results) < self::PAGE_SIZE) {
                break;
            }
        }

        Log::info('GmbFinderService: done', ['keyword' => $keyword, 'seen' => $seen, 'no_website' => count($leads)]);

        return ['leads' => $leads, 'seen' => $seen, 'with_website' => $withWebsite];
    }
}
