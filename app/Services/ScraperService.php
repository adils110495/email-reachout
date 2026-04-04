<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ScraperService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout'         => 20,
            'connect_timeout' => 10,
            'verify'          => false,
            'allow_redirects' => ['max' => 5],
        ]);
    }

    /**
     * Fetch the full HTML of a webpage.
     * Also tries /contact and /about pages to maximise email discovery.
     */
    public function fetch(string $url): string
    {
        $html = '';

        // Normalise the URL
        $url = $this->normaliseUrl($url);

        // Fetch the homepage
        $html .= $this->fetchPage($url);

        // Try common contact/about pages if no email found yet
        $contactPages = ['/contact', '/contact-us', '/about', '/about-us', '/team'];

        foreach ($contactPages as $path) {
            $pageUrl = rtrim($url, '/') . $path;
            $pageHtml = $this->fetchPage($pageUrl);

            if (! empty($pageHtml)) {
                $html .= $pageHtml;
                // Stop after the first successful extra page
                break;
            }
        }

        return $html;
    }

    /**
     * Fetch a single page and return its HTML, or an empty string on failure.
     */
    private function fetchPage(string $url): string
    {
        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return (string) $response->getBody();
            }

        } catch (RequestException $e) {
            // Log only the first failed attempt to avoid log spam
            Log::debug('ScraperService: Failed to fetch page', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return '';
    }

    /**
     * Ensure the URL has a scheme (default: https).
     */
    private function normaliseUrl(string $url): string
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        return $url;
    }
}
