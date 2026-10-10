<?php

namespace App\Sequencer\Support;

/**
 * Builds the public URLs embedded in outgoing mail. They are absolute and built
 * from config (never from the current request) because the job that renders the
 * email runs in a queue worker with no request.
 */
final class TrackingUrls
{
    public static function base(): string
    {
        return rtrim((string) config('sequencer.tracking.public_url'), '/');
    }

    public static function open(string $token): string
    {
        return self::base().'/track/open/'.$token;
    }

    public static function click(string $token): string
    {
        return self::base().'/track/click/'.$token;
    }

    public static function unsubscribe(string $token): string
    {
        return self::base().'/unsubscribe/'.$token;
    }

    /** True for any URL that points at our own tracking / unsubscribe endpoints. */
    public static function isInternal(string $url): bool
    {
        $base = self::base();

        if ($base !== '' && str_starts_with($url, $base)) {
            $path = substr($url, strlen($base));

            return (bool) preg_match('#^/(track/(open|click)|unsubscribe)/#', $path);
        }

        return (bool) preg_match('#/(track/(open|click)|unsubscribe)/[A-Za-z0-9]{16,}#', $url);
    }
}
