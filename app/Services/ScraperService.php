<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ScraperService
{
    /**
     * Pages tried after the homepage, best-first.
     *
     * Contact and about pages come first because they carry the address a
     * business actually wants to be reached on. Careers and jobs pages are
     * worth the extra request too - they routinely publish careers@ / hr@ /
     * jobs@, which is often the only address a company puts in public - but
     * they rank last, since a recruiting inbox is a weaker outreach target
     * than the one on the contact page.
     */
    private const CONTACT_PATHS = [
        '/contact',
        '/contact-us',
        '/about',
        '/about-us',
        '/team',
        '/careers',
        '/career',
        '/jobs',
    ];

    /**
     * Pages beyond the homepage collected by default.
     *
     * More than one, because the loop used to stop at the first page that
     * answered - so on any site with a /contact page, /careers was never
     * reached. Capped at three so the worst case stays inside the callers'
     * timeouts.
     */
    private const DEFAULT_EXTRA_PAGES = 3;

    /**
     * Path fragments that mark a link as worth following on the second pass.
     * Matched against the URL path, so "/career/publisher-outreach-manager/"
     * qualifies through "career".
     */
    private const LINK_HINTS = [
        'career', 'job', 'vacanc', 'hiring', 'opening', 'recruit',
        'contact', 'about', 'team', 'people', 'staff', 'imprint', 'impressum',
    ];

    private Client $client;

    /**
     * URLs already requested during the current fetch()/fetchUrls() run, so a
     * page linked from two places is not downloaded twice.
     *
     * @var string[]
     */
    private array $fetched = [];

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
     * Also tries the pages that commonly carry a published address, in the
     * order they are most likely to hold the *primary* one.
     *
     * @param  float|null  $budgetSeconds  Overall wall-clock budget. Once it is
     *   spent no further pages are tried, and each individual request is capped
     *   at whatever is left. Pass this whenever the caller is a web request:
     *   without it a site that times out on every path costs minutes, which is
     *   fine on the queue and far too slow in front of a user.
     * @param  int  $maxExtraPages  How many pages beyond the homepage may be
     *   collected. Pass 0 when only the homepage matters (a title / og:site_name
     *   lookup, say) - every extra request is then pure waste.
     */
    public function fetch(string $url, ?float $budgetSeconds = null, int $maxExtraPages = self::DEFAULT_EXTRA_PAGES): string
    {
        $startedAt = microtime(true);
        $html      = '';

        // Normalise the URL
        $url    = $this->normaliseUrl($url);
        $origin = $this->originOf($url);
        $path   = trim((string) parse_url($url, PHP_URL_PATH), '/');

        $remaining = function () use ($startedAt, $budgetSeconds): ?float {
            return $budgetSeconds === null ? null : $budgetSeconds - (microtime(true) - $startedAt);
        };

        $this->fetched = [];

        // A caller who passed a deep URL is pointing at a specific page - a job
        // posting, a team page - and that page is very often the only one with
        // an address on it. Read it before anything is guessed.
        if ($path !== '') {
            $html .= $this->fetchTracked($url, $remaining());
        }

        // The homepage, which is where the company name usually comes from.
        $html .= $this->fetchTracked($origin, $remaining());

        $collected = 0;

        foreach (self::CONTACT_PATHS as $candidate) {
            if ($collected >= $maxExtraPages) {
                break;
            }

            $left = $remaining();

            // Under a second left is not worth a request.
            if ($left !== null && $left < 1.0) {
                break;
            }

            $pageHtml = $this->fetchTracked(rtrim($origin, '/') . $candidate, $left);

            if (! empty($pageHtml)) {
                $html .= $pageHtml;
                $collected++;
            }
        }

        return $html;
    }

    /**
     * Same-domain links in $html that look like they lead to a contact or
     * careers page, as absolute URLs.
     *
     * Guessed paths only reach pages the site happens to name conventionally.
     * A careers *listing* answers on /career but publishes nothing itself - the
     * address lives on the individual posting it links to. This reads those
     * links back out of the HTML already fetched, so the second pass costs no
     * extra guessing.
     *
     * @return string[]
     */
    public function discoverLinks(string $html, string $baseUrl, int $limit = 3): array
    {
        if ($html === '' || $limit < 1) {
            return [];
        }

        $origin = $this->originOf($this->normaliseUrl($baseUrl));
        $host   = parse_url($origin, PHP_URL_HOST);

        if (! $host) {
            return [];
        }

        preg_match_all('/href=["\']([^"\'#]+)["\']/i', $html, $matches);

        $found = [];

        foreach ($matches[1] ?? [] as $href) {
            $absolute = $this->absoluteUrl(trim(html_entity_decode($href, ENT_QUOTES)), $origin);

            if ($absolute === null || isset($found[$absolute])) {
                continue;
            }

            // Never leave the site being scraped.
            if (parse_url($absolute, PHP_URL_HOST) !== $host) {
                continue;
            }

            // Already read on this run - re-reading it cannot add anything.
            if (in_array($absolute, $this->fetched, true)) {
                continue;
            }

            $urlPath = strtolower((string) parse_url($absolute, PHP_URL_PATH));

            if ($urlPath === '' || $urlPath === '/') {
                continue;
            }

            if ($this->isNoiseLink($urlPath)) {
                continue;
            }

            foreach (self::LINK_HINTS as $hint) {
                if (str_contains($urlPath, $hint)) {
                    $found[$absolute] = true;
                    break;
                }
            }

            if (count($found) >= $limit) {
                break;
            }
        }

        return array_keys($found);
    }

    /**
     * Fetch several URLs under one shared budget and concatenate what comes back.
     *
     * @param  string[]  $urls
     */
    public function fetchUrls(array $urls, ?float $budgetSeconds = null): string
    {
        $startedAt = microtime(true);
        $html      = '';

        foreach ($urls as $url) {
            $left = $budgetSeconds === null ? null : $budgetSeconds - (microtime(true) - $startedAt);

            if ($left !== null && $left < 1.0) {
                break;
            }

            $html .= $this->fetchTracked($url, $left);
        }

        return $html;
    }

    /**
     * Fetch a page and remember the URL, so nothing is requested twice per run.
     *
     * Tracked in the same shape discoverLinks() produces (query and trailing
     * slash removed), so "/career" and "/career/" count as the one page they are.
     */
    private function fetchTracked(string $url, ?float $timeout = null): string
    {
        $key = rtrim(strtok($url, '?') ?: $url, '/');

        if (in_array($key, $this->fetched, true)) {
            return '';
        }

        $this->fetched[] = $key;

        return $this->fetchPage($url, $timeout);
    }

    /** scheme://host[:port] for a URL. */
    private function originOf(string $url): string
    {
        $parts = parse_url($url);

        if (empty($parts['host'])) {
            return rtrim($url, '/');
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** Resolve an href against the site origin; null for anything unusable. */
    private function absoluteUrl(string $href, string $origin): ?string
    {
        if ($href === '' || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')
            || str_starts_with($href, 'javascript:') || str_starts_with($href, 'data:')) {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return rtrim(strtok($href, '?'), '/');
        }

        if (str_starts_with($href, '//')) {
            return rtrim(strtok('https:'.$href, '?'), '/');
        }

        if (str_starts_with($href, '/')) {
            return rtrim(strtok($origin.$href, '?'), '/');
        }

        return null; // relative path - not worth resolving against an unknown base
    }

    /** Feeds, API endpoints, taxonomy archives and assets are never contact pages. */
    private function isNoiseLink(string $urlPath): bool
    {
        static $noise = [
            '/wp-json', '/wp-admin', '/wp-content', '/wp-includes', '/oembed',
            '/feed', '/tag/', '/category/', '/categories/', '/author/', '/page/',
            '/comment', '/cart', '/checkout', '/login', '/register',
        ];

        foreach ($noise as $fragment) {
            if (str_contains($urlPath, $fragment)) {
                return true;
            }
        }

        return (bool) preg_match('/\.(pdf|jpe?g|png|gif|svg|webp|css|js|zip|docx?|xml)$/i', $urlPath);
    }

    /**
     * Fetch a single page and return its HTML, or an empty string on failure.
     *
     * @param  float|null  $timeout  Per-request cap; null uses the client default.
     */
    private function fetchPage(string $url, ?float $timeout = null): string
    {
        try {
            $options = [
                'headers' => [
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
            ];

            if ($timeout !== null) {
                $options['timeout']         = max(1.0, $timeout);
                $options['connect_timeout'] = max(1.0, min(10.0, $timeout));
            }

            $response = $this->client->get($url, $options);

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
