<?php

namespace App\Sequencer\Services;

use App\Models\Sequence;
use App\Sequencer\Support\Tz;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Decides when a sequence may send: allowed weekdays + a daily time range,
 * evaluated in the sequence's timezone (falling back to its owner's).
 */
class SendingWindowService
{
    public function isWithinWindow(Sequence $sequence, CarbonInterface $at): bool
    {
        [$days, $start, $end] = $this->settings($sequence);
        $local = CarbonImmutable::instance($at)->setTimezone($sequence->effectiveTimezone());

        if (! in_array($local->dayOfWeekIso, $days, true)) {
            return false;
        }

        return $local >= $this->at($local, $start) && $local < $this->at($local, $end);   // end is exclusive
    }

    /**
     * The earliest moment >= $at at which sending is allowed. Returns $at itself
     * when it is already inside the window. Returned in the app timezone, ready to store.
     */
    public function nextAllowed(Sequence $sequence, CarbonInterface $at): CarbonImmutable
    {
        [$days, $start, $end] = $this->settings($sequence);
        $local = CarbonImmutable::instance($at)->setTimezone($sequence->effectiveTimezone());

        // At most a week (+1 for "later today") of candidates can be needed.
        for ($i = 0; $i <= 7; $i++) {
            $day = $local->addDays($i);

            if (! in_array($day->dayOfWeekIso, $days, true)) {
                continue;
            }

            $windowStart = $this->at($day, $start);
            $windowEnd = $this->at($day, $end);

            if ($i === 0) {
                if ($local >= $windowEnd) {
                    continue;                                  // today's window is over
                }

                return Tz::app($local < $windowStart ? $windowStart : $local);
            }

            return Tz::app($windowStart);
        }

        // Unreachable with a non-empty days list (settings() guarantees one).
        return Tz::app($local);
    }

    /** First instant of the next calendar day in the sequence timezone (for daily-limit overflow). */
    public function startOfNextDay(Sequence $sequence, CarbonInterface $at): CarbonImmutable
    {
        return Tz::app(CarbonImmutable::instance($at)
            ->setTimezone($sequence->effectiveTimezone())
            ->addDay()
            ->startOfDay());
    }

    /** The "daily limit" day bucket (Y-m-d) in the sequence timezone. */
    public function dayKey(Sequence $sequence, CarbonInterface $at): string
    {
        return CarbonImmutable::instance($at)->setTimezone($sequence->effectiveTimezone())->toDateString();
    }

    /** @return array{0: list<int>, 1: string, 2: string} */
    private function settings(Sequence $sequence): array
    {
        $days = array_values(array_unique(array_map('intval', (array) $sequence->sending_days)));
        $days = array_values(array_filter($days, fn (int $d) => $d >= 1 && $d <= 7));
        if ($days === []) {
            $days = config('sequencer.defaults.sending_days', [1, 2, 3, 4, 5]);
        }

        $start = substr((string) ($sequence->sending_start_time ?: config('sequencer.defaults.sending_start')), 0, 8);
        $end = substr((string) ($sequence->sending_end_time ?: config('sequencer.defaults.sending_end')), 0, 8);

        // A zero or inverted range would make nextAllowed() skip every day: treat it as "all day".
        if ($this->seconds($start) >= $this->seconds($end)) {
            [$start, $end] = ['00:00:00', '23:59:59'];
        }

        return [$days, $start, $end];
    }

    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        return $day->setTimeFromTimeString($time);
    }

    private function seconds(string $time): int
    {
        $parts = array_map('intval', explode(':', $time) + [0, 0, 0]);

        return $parts[0] * 3600 + $parts[1] * 60 + ($parts[2] ?? 0);
    }
}
