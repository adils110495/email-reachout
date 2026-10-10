<?php

namespace App\Sequencer\Support;

use Carbon\CarbonImmutable;
use Throwable;

/** Small, dependency-free RFC 5322 header helpers (pure functions, unit tested). */
final class MessageHeaders
{
    /**
     * Parse a raw header block into [lower-case name => unfolded value].
     * When a header repeats (Received, ...) the first occurrence wins.
     *
     * @return array<string, string>
     */
    public static function parse(string $raw): array
    {
        // Stop at the blank line that ends the header block.
        $raw = preg_split("/\r?\n\r?\n/", $raw, 2)[0] ?? $raw;
        // Unfold continuation lines.
        $raw = preg_replace("/\r?\n[ \t]+/", ' ', $raw) ?? $raw;

        $headers = [];
        foreach (preg_split("/\r?\n/", $raw) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));
            if ($name !== '' && ! isset($headers[$name])) {
                $headers[$name] = trim($value);
            }
        }

        return $headers;
    }

    /** All <id> tokens in a header value, without the angle brackets. @return list<string> */
    public static function messageIds(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        preg_match_all('/<([^<>\s]+)>/', $value, $m);

        return array_values(array_unique($m[1]));
    }

    /** First e-mail address in a From/To style header, lower-cased. */
    public static function address(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/<([^<>\s]+@[^<>\s]+)>/', $value, $m)) {
            return strtolower(trim($m[1]));
        }

        if (preg_match('/([A-Za-z0-9._%+\-\']+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})/', $value, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    /** Decode RFC 2047 encoded words ("=?UTF-8?B?...?=") to UTF-8. */
    public static function decode(string $value): string
    {
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false ? $value : $decoded;
    }

    public static function date(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            // In the app timezone: the result is saved (received_at) and compared with sent_at in SQL.
            return Tz::app(CarbonImmutable::parse($value));
        } catch (Throwable) {
            return null;
        }
    }

    /** "Re: Fwd: Hello" -> "hello" for comparing a reply subject with the original. */
    public static function normalizeSubject(string $subject): string
    {
        $subject = trim(self::decode($subject));
        $subject = preg_replace('/^(\s*(re|fwd?|aw|sv)\s*:\s*)+/i', '', $subject) ?? $subject;

        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $subject) ?? $subject));
    }
}
