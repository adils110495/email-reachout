<?php

namespace App\Sequencer\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Throwable;

/** Timezone helpers: store in the app timezone, display in the signed-in user's timezone. */
final class Tz
{
    /**
     * The same instant in the app timezone - the zone the database stores and reads.
     *
     * Eloquent and the query builder write a Carbon's wall-clock time as it is; they do not
     * convert it. So a time computed in another zone (a sequence's sending window, the Date
     * header of an incoming mail) must pass through here before it is saved or bound into
     * SQL, or it lands in the database shifted by the difference between the two zones.
     */
    public static function app(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone(config('app.timezone'));
    }

    /** Format a timestamp in the current user's timezone. */
    public static function format(?CarbonInterface $at, string $format = 'd M Y, H:i', ?string $timezone = null): string
    {
        if ($at === null) {
            return '—';
        }

        $timezone ??= auth()->user()?->timezoneName() ?? config('app.timezone');

        return $at->copy()->setTimezone(self::valid($timezone) ? $timezone : config('app.timezone'))->format($format);
    }

    public static function valid(?string $timezone): bool
    {
        if ($timezone === null || $timezone === '') {
            return false;
        }

        try {
            new DateTimeZone($timezone);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<string> every IANA timezone identifier, for <select> options */
    public static function all(): array
    {
        return DateTimeZone::listIdentifiers();
    }
}
