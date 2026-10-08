<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class EmailExtractorService
{
    /**
     * Bytes of HTML scanned per pass. Well under any PCRE limit, and small
     * enough that strip_tags() never works on a multi-megabyte string.
     */
    private const SLICE_BYTES = 262144; // 256 KB

    /** Overlap between slices - far longer than the longest legal address. */
    private const OVERLAP_BYTES = 1024;

    /**
     * Domains that are almost never real contact emails —
     * skip these to avoid harvesting generic/example addresses.
     */
    private array $blacklistedDomains = [
        'example.com',
        'example.org',
        'test.com',
        'sentry.io',
        'wixpress.com',
        'squarespace.com',
        'wordpress.com',
        'schema.org',
        'w3.org',
    ];

    /**
     * Extract unique email addresses from an HTML string.
     *
     * @return string[]
     */
    public function extract(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $emails   = [];
        $allFound = [];

        // Scanned in slices rather than in one go. A domain search concatenates
        // every page it fetched, which on a content-heavy site is several
        // megabytes - and PCRE stops matching on a subject that large, returning
        // false and (before this) silently yielding no addresses at all. Slices
        // keep every subject small enough for the engine to finish.
        foreach ($this->slices($html) as $slice) {
            // 1. mailto: links (highest quality)
            $allFound = array_merge($allFound, $this->matchAll(
                '/href=["\']mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})["\']/',
                $slice,
                1,
            ));

            // 2. Plain text scan. Tags are stripped first so markup cannot
            //    produce false positives.
            $allFound = array_merge($allFound, $this->matchAll(
                '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
                strip_tags($slice),
                0,
            ));
        }

        foreach ($allFound as $email) {
            $email = strtolower(trim($email));

            if ($this->isValid($email)) {
                $emails[] = $email;
            }
        }

        // Return unique emails, prioritising those from mailto: links
        return array_values(array_unique($emails));
    }

    /**
     * Split HTML into slices small enough for PCRE to scan reliably.
     *
     * Slices overlap by OVERLAP_BYTES so an address straddling a boundary is
     * still matched whole in one of them; duplicates are collapsed later.
     *
     * @return iterable<string>
     */
    private function slices(string $html): iterable
    {
        $length = strlen($html);

        if ($length <= self::SLICE_BYTES) {
            yield $html;

            return;
        }

        for ($offset = 0; $offset < $length; $offset += self::SLICE_BYTES) {
            // Reach back over the boundary, except at the very start.
            $start = $offset === 0 ? 0 : $offset - self::OVERLAP_BYTES;

            yield substr($html, $start, self::SLICE_BYTES + self::OVERLAP_BYTES);
        }
    }

    /**
     * preg_match_all that reports failure instead of hiding it.
     *
     * preg_match_all() returns false on an engine error (backtrack limit, JIT
     * stack limit, a subject it will not scan) and leaves the matches array
     * empty - which reads exactly like "this page has no addresses". That is
     * how a page with a perfectly visible address came back empty. Now the
     * failure is logged and the caller keeps whatever the other patterns found.
     *
     * @return string[]
     */
    private function matchAll(string $pattern, string $subject, int $group): array
    {
        if ($subject === '') {
            return [];
        }

        $matches = [];
        $result  = @preg_match_all($pattern, $subject, $matches);

        if ($result === false) {
            Log::warning('EmailExtractorService: pattern failed', [
                'error'          => preg_last_error_msg(),
                'subject_length' => strlen($subject),
            ]);

            return [];
        }

        return $matches[$group] ?? [];
    }

    /**
     * Validate that an email address looks real and isn't blacklisted.
     */
    private function isValid(string $email): bool
    {
        // PHP built-in validation
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1));

        // Skip blacklisted domains
        foreach ($this->blacklistedDomains as $blacklisted) {
            if ($domain === $blacklisted || str_ends_with($domain, '.' . $blacklisted)) {
                return false;
            }
        }

        // Skip obviously generic/no-reply addresses
        $localPart = strtolower(explode('@', $email)[0]);
        $skipPrefixes = ['noreply', 'no-reply', 'donotreply', 'do-not-reply', 'bounce'];

        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($localPart, $prefix)) {
                return false;
            }
        }

        return true;
    }
}
