<?php

namespace App\Services;

class EmailExtractorService
{
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
        if (empty($html)) {
            return [];
        }

        $emails = [];

        // 1. Extract from mailto: links (highest quality)
        preg_match_all(
            '/href=["\']mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})["\']/',
            $html,
            $mailtoMatches
        );

        // 2. Extract from obfuscated mailto patterns (e.g. data-email attributes)
        preg_match_all(
            '/data-(?:email|mail|cfemail)=["\']([^"\']+)["\']/',
            $html,
            $dataMatches
        );

        // 3. General regex scan of all text (catches plain-text emails in HTML)
        // Strip HTML tags first to avoid false positives from markup
        $plainText = strip_tags($html);
        preg_match_all(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
            $plainText,
            $textMatches
        );

        $allFound = array_merge(
            $mailtoMatches[1] ?? [],
            $textMatches[0] ?? [],
        );

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
