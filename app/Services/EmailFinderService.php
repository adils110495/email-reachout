<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Finds email addresses for a company domain, or for a named person at that
 * domain. Built on the services the Leads module already uses:
 *
 *   ScraperService         fetches the homepage plus a contact/about page
 *   EmailExtractorService  pulls addresses out of that HTML
 *   EmailVerifierService   scores each candidate so results can be ranked
 *
 * Domain search returns addresses that are actually published on the site.
 * Person search has nothing to scrape, so it generates the common corporate
 * name patterns and leans on verification to rank them - each candidate is
 * flagged `guessed` so the UI never presents a guess as a confirmed address.
 */
class EmailFinderService
{
    /**
     * Wall-clock budget for the scrape behind a domain search, in seconds.
     * Sized to stay clear of a typical 30s PHP execution limit once the
     * verification of the addresses that come back is added on top.
     */
    /**
     * Total budget for a Finder lookup, in seconds.
     *
     * Sized against what a real slow site actually costs. Measured on one:
     * homepage plus the eight guessed paths is ~22s, then the careers listing
     * is another ~3s, and the job postings it links to another ~6s - about 35s
     * end to end. 60s leaves room for a site slower than that.
     *
     * The ceiling is PHP's max_execution_time, 120s in docker/php/php.ini
     * (Apache's ProxyTimeout is 300s, so it is not the binding limit).
     */
    public const WEB_BUDGET = 60.0;

    /**
     * Share of the budget held back from the first pass so link-following
     * always has room.
     *
     * Without a reserve the guessed paths eat the whole budget on a slow site
     * and the follow-up never runs - which is exactly the case that needs it,
     * since the guesses evidently found nothing. Unused when the first pass
     * succeeds, so the fast path costs nothing.
     *
     * A share rather than a fixed number of seconds: a fixed 12s reserve would
     * be most of a bulk item's 20s budget, starving the pass that usually finds
     * the address.
     */
    private const FOLLOW_RESERVE_RATIO = 0.4;

    private const FOLLOW_RESERVE_MIN = 6.0;

    private const FOLLOW_RESERVE_MAX = 25.0;

    /** Links followed per round. */
    private const FOLLOW_PAGES = 4;

    /**
     * How many rounds of link-following to run.
     *
     * Two, because a careers *listing* is itself a hop: /career answers but
     * publishes nothing, and the address is on the posting it links to. One
     * round only reaches the listing.
     */
    private const FOLLOW_DEPTH = 2;

    /**
     * Seconds that must still be left before another round is worth starting.
     * Below this it would only manage a truncated request or two.
     */
    private const FOLLOW_MIN_BUDGET = 4.0;

    /**
     * Patterns tried for a person search, most-common first. Placeholders:
     * {f} first initial, {first} first name, {l} last initial, {last} surname.
     */
    private const PATTERNS = [
        '{first}.{last}' => 'first.last',
        '{first}'        => 'first',
        '{f}{last}'      => 'flast',
        '{first}{last}'  => 'firstlast',
        '{first}_{last}' => 'first_last',
        '{last}.{first}' => 'last.first',
        '{first}-{last}' => 'first-last',
        '{f}.{last}'     => 'f.last',
        '{last}'         => 'last',
        '{first}{l}'     => 'firstl',
    ];

    public function __construct(
        private readonly ScraperService $scraper,
        private readonly EmailExtractorService $extractor,
        private readonly EmailVerifierService $verifier,
    ) {}

    /**
     * Scrape a company website and return every address published on it,
     * ranked best-first.
     *
     * @return array{
     *     domain:string, url:string, company:?string, scraped:bool,
     *     candidates:array<int, array<string,mixed>>
     * }
     */
    public function findByDomain(string $input, ?float $budgetSeconds = self::WEB_BUDGET): array
    {
        $startedAt = microtime(true);

        $domain = $this->normaliseDomain($input);
        $url    = 'https://'.$domain;

        if ($domain === '') {
            return ['domain' => '', 'url' => '', 'company' => null, 'scraped' => false, 'candidates' => []];
        }

        // The domain comes straight from a user, and the next line makes the
        // server fetch it. Refuse anything that resolves inside the network so
        // this cannot be pointed at internal services or cloud metadata.
        if (! $this->isPubliclyRoutable($domain)) {
            Log::warning('EmailFinderService: refused a non-public host', ['domain' => $domain]);

            return ['domain' => $domain, 'url' => $url, 'company' => null, 'scraped' => false, 'candidates' => []];
        }

        // If the user pasted a full page URL, start there. They pointed at that
        // page for a reason - on many sites the only published address sits on
        // one deep page (a job posting, a team bio) and nowhere else.
        $entryUrl = $this->entryUrl($input, $domain);

        $remaining = fn (): ?float => $budgetSeconds === null
            ? null
            : $budgetSeconds - (microtime(true) - $startedAt);

        $html = '';

        // The first pass keeps its hands off the reserve, so that whatever it
        // fails to find, the follow-up below still has time to look for.
        $firstPassBudget = $budgetSeconds === null
            ? null
            : max(self::FOLLOW_MIN_BUDGET, $budgetSeconds - $this->followReserve($budgetSeconds));

        try {
            $html = $this->scraper->fetch($entryUrl, $firstPassBudget);
        } catch (\Throwable $e) {
            Log::warning('EmailFinderService: scrape failed', ['domain' => $domain, 'error' => $e->getMessage()]);
        }

        $scan     = $this->scanEmails($html, $domain);
        $onDomain = $scan['on_domain'];

        Log::info('EmailFinderService: first pass', [
            'domain'     => $domain,
            'entry'      => $entryUrl,
            'html_bytes' => strlen($html),
            'all_emails' => count($scan['all']),
            'on_domain'  => count($onDomain),
        ]);

        // Nothing on the entry page, the homepage or any guessed path. Before
        // giving up, walk the contact/careers links those pages actually
        // contain. discoverLinks() skips anything already fetched, so each round
        // moves a level deeper on its own.
        for ($round = 0; $onDomain === [] && $html !== '' && $round < self::FOLLOW_DEPTH; $round++) {
            $left = $remaining();

            if ($left !== null && $left < self::FOLLOW_MIN_BUDGET) {
                break;
            }

            $links = $this->scraper->discoverLinks($html, $url, self::FOLLOW_PAGES);

            if ($links === []) {
                break;
            }

            try {
                $html    .= $this->scraper->fetchUrls($links, $left);
                $scan     = $this->scanEmails($html, $domain);
                $onDomain = $scan['on_domain'];

                Log::info('EmailFinderService: followed links', [
                    'domain'     => $domain,
                    'round'      => $round + 1,
                    'links'      => $links,
                    'html_bytes' => strlen($html),
                    'all_emails' => count($scan['all']),
                    'found'      => count($onDomain),
                ]);
            } catch (\Throwable $e) {
                Log::warning('EmailFinderService: link follow failed', [
                    'domain' => $domain, 'round' => $round + 1, 'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        $candidates = [];

        foreach (array_slice($onDomain, 0, 15) as $email) {
            $result = $this->verifier->verify($email);

            $candidates[] = [
                'email'      => $email,
                'status'     => $result['status'],
                'score'      => $result['score'],
                'reason'     => $result['reason'],
                'checks'     => $result['checks'],
                'source'     => 'website',
                'guessed'    => false,
                'type'       => $result['checks']['role'] ? 'generic' : 'personal',
                'pattern'    => null,
            ];
        }

        return [
            'domain'     => $domain,
            'url'        => $url,
            'company'    => $this->extractCompanyName($html) ?: null,
            'scraped'    => $html !== '',
            'candidates' => $this->rank($candidates),
        ];
    }

    /**
     * Generate and score the likely addresses for a named person at a domain.
     *
     * Nothing here is scraped, so every candidate is `guessed`. When the SMTP
     * probe is enabled the verifier can actually confirm one of them; without
     * it, the ranking reflects pattern frequency and domain health only.
     *
     * @return array{
     *     domain:string, person:string,
     *     candidates:array<int, array<string,mixed>>
     * }
     */
    public function findByPerson(string $fullName, string $domainInput): array
    {
        $domain = $this->normaliseDomain($domainInput);
        $name   = $this->splitName($fullName);

        if ($domain === '' || $name['first'] === '') {
            return ['domain' => $domain, 'person' => trim($fullName), 'candidates' => []];
        }

        $seen       = [];
        $candidates = [];
        // Pattern frequency in the wild, roughly: the first entry is by far the
        // most common, and confidence tapers off down the list.
        $baseScore  = 70;

        foreach (self::PATTERNS as $pattern => $label) {
            $local = strtr($pattern, [
                '{first}' => $name['first'],
                '{last}'  => $name['last'],
                '{f}'     => substr($name['first'], 0, 1),
                '{l}'     => substr($name['last'], 0, 1),
            ]);

            // Patterns needing a surname collapse to a bare first name when one
            // was not supplied - skip those duplicates.
            $local = trim($local, '._-');

            if ($local === '' || isset($seen[$local])) {
                continue;
            }
            $seen[$local] = true;

            $email  = $local.'@'.$domain;
            $result = $this->verifier->verify($email);

            // A guess can never outrank a scraped address, so cap it: the domain
            // checks are real, the mailbox itself is not confirmed.
            $score = $result['status'] === 'invalid'
                ? $result['score']
                : (int) min($baseScore, $result['score']);

            $candidates[] = [
                'email'   => $email,
                'status'  => $result['status'],
                'score'   => $score,
                'reason'  => $result['status'] === 'invalid'
                    ? $result['reason']
                    : 'Pattern "'.$label.'" - domain accepts mail, mailbox unconfirmed.',
                'checks'  => $result['checks'],
                'source'  => 'pattern',
                'guessed' => true,
                'type'    => 'personal',
                'pattern' => $label,
            ];

            $baseScore = max(25, $baseScore - 5);
        }

        return [
            'domain'     => $domain,
            'person'     => trim($fullName),
            'candidates' => $this->rank($candidates),
        ];
    }

    /**
     * Pull a company name out of scraped HTML: og:site_name first (most
     * reliable), then the <title> with its trailing tagline removed.
     */
    public function extractCompanyName(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) {
            $name = trim(html_entity_decode($m[1], ENT_QUOTES));
            if ($name !== '') {
                return $name;
            }
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES));
            $title = trim(preg_split('/\s*[\-\|]\s*/', $title)[0] ?? '');
            if ($title !== '') {
                return $title;
            }
        }

        return '';
    }

    /**
     * Seconds to hold back from the first pass for link-following.
     *
     * Scales with the budget so the split stays sensible at both ends: a 60s
     * web lookup reserves 24s, a 20s bulk item reserves 8s.
     */
    private function followReserve(float $budgetSeconds): float
    {
        return min(
            self::FOLLOW_RESERVE_MAX,
            max(self::FOLLOW_RESERVE_MIN, $budgetSeconds * self::FOLLOW_RESERVE_RATIO),
        );
    }

    /**
     * The URL a domain search should start from.
     *
     * normaliseDomain() deliberately throws the path away - it answers "which
     * company is this?", and the SSRF check and the email filter both need the
     * bare host. But the path the user typed is a signal in its own right: if
     * they pasted /career/publisher-outreach-manager/, that is where they saw
     * the address. So the host comes from the validated domain (never from the
     * raw input) and only the path is carried over.
     */
    private function entryUrl(string $input, string $domain): string
    {
        $raw = trim($input);

        if ($raw === '' || str_contains($raw, '@')) {
            return 'https://'.$domain;
        }

        if (! str_starts_with($raw, 'http://') && ! str_starts_with($raw, 'https://')) {
            $raw = 'https://'.$raw;
        }

        $path = (string) parse_url($raw, PHP_URL_PATH);

        if ($path === '' || $path === '/') {
            return 'https://'.$domain;
        }

        // Rebuilt from the validated domain, so a crafted input cannot redirect
        // the fetch at a different host than the one that passed the SSRF check.
        return 'https://'.$domain.'/'.ltrim($path, '/');
    }

    /**
     * Extract every address in $html once, and split out the ones belonging to
     * the company being searched.
     *
     * Both halves come back because the two counts together say what happened:
     * "20 addresses, 0 on this domain" is a filter problem, "0 addresses" is a
     * fetch or parse problem. Extracting once keeps a multi-megabyte scan off
     * the critical path twice.
     *
     * A site routinely embeds a partner's, an agency's or a plugin vendor's
     * address; those are noise for this lookup.
     *
     * @return array{all: string[], on_domain: string[]}
     */
    private function scanEmails(string $html, string $domain): array
    {
        $all = $html === '' ? [] : $this->extractor->extract($html);

        return [
            'all'       => $all,
            'on_domain' => array_values(array_filter(
                $all,
                fn (string $email) => $this->belongsToDomain($email, $domain),
            )),
        ];
    }

    /** Strip scheme, www., path and port from anything the user typed. */
    public function normaliseDomain(string $input): string
    {
        $input = strtolower(trim($input));

        if ($input === '') {
            return '';
        }

        // An email pasted into the domain box: take the part after the @.
        if (str_contains($input, '@')) {
            $input = (string) substr((string) strrchr($input, '@'), 1);
        }

        if (! str_starts_with($input, 'http://') && ! str_starts_with($input, 'https://')) {
            $input = 'https://'.$input;
        }

        $host = parse_url($input, PHP_URL_HOST) ?: '';
        $host = preg_replace('/^www\./', '', $host) ?? '';

        // Reject anything that is not a plausible hostname before it reaches DNS.
        return preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $host) ? $host : '';
    }

    /** @return array{first:string, last:string} lowercased, accent-free name parts */
    private function splitName(string $fullName): array
    {
        $clean = preg_replace('/[^\p{L}\s\-]+/u', '', trim($fullName)) ?? '';
        $clean = $this->asciiFold($clean);
        $parts = preg_split('/\s+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return [
            'first' => strtolower($parts[0] ?? ''),
            'last'  => strtolower(count($parts) > 1 ? (string) end($parts) : ''),
        ];
    }

    /** Best-effort transliteration so "Renée Müller" yields "renee.muller". */
    private function asciiFold(string $value): string
    {
        if (function_exists('transliterator_transliterate')) {
            $folded = @transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
            if (is_string($folded) && $folded !== '') {
                return $folded;
            }
        }

        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return is_string($folded) ? preg_replace('/[^A-Za-z\s\-]/', '', $folded) ?? $value : $value;
    }

    /**
     * True when every address the host resolves to is on the public internet.
     *
     * normaliseDomain() already rejects bare IPs and single-label names like
     * "localhost", but a public DNS name can still point at 127.0.0.1, a
     * 10.x address, or a cloud metadata endpoint - so check what it actually
     * resolves to, not just how it is spelled. A host that does not resolve is
     * treated as not routable: there is nothing to fetch either way.
     */
    private function isPubliclyRoutable(string $host): bool
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        $addresses = array_values(array_filter(array_map(
            static fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            // FILTER_FLAG_NO_PRIV_RANGE covers 10/8, 172.16/12, 192.168/16 and
            // fc00::/7; NO_RES_RANGE covers loopback, link-local (including
            // 169.254.169.254) and the other reserved blocks.
            $public = filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );

            if ($public === false) {
                return false;
            }
        }

        return true;
    }

    /** True when the address is at the searched domain or one of its subdomains. */
    private function belongsToDomain(string $email, string $domain): bool
    {
        $emailDomain = strtolower((string) substr((string) strrchr($email, '@'), 1));

        return $emailDomain === $domain || str_ends_with($emailDomain, '.'.$domain);
    }

    /**
     * Best candidate first: confirmed before guessed, higher score first, then
     * a personal address ahead of a generic info@ one.
     *
     * @param  array<int, array<string,mixed>>  $candidates
     * @return array<int, array<string,mixed>>
     */
    private function rank(array $candidates): array
    {
        usort($candidates, static function (array $a, array $b) {
            return [$a['guessed'], -$a['score'], $a['type'] === 'generic']
               <=> [$b['guessed'], -$b['score'], $b['type'] === 'generic'];
        });

        return $candidates;
    }
}
