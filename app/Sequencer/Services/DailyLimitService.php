<?php

namespace App\Sequencer\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Per-day send quotas that concurrent workers cannot overshoot.
 *
 * reserve() is a single conditional UPDATE ("count < limit"), so MySQL's row lock
 * serialises racing workers: exactly `limit` reservations can ever succeed per
 * scope per day, no matter how many workers run. The database (not Redis) holds
 * the truth so a cache flush can never reset a day's usage.
 *
 * Lock-ordering note: the UPDATE is attempted FIRST. Creating the row first
 * (INSERT IGNORE, which takes a shared lock on a duplicate) and then updating it
 * makes two workers upgrade S -> X on the same row and deadlock. The row is only
 * created on the first use of the day, when the UPDATE matches nothing.
 */
class DailyLimitService
{
    public const SCOPE_SEQUENCE = 'sequence';

    public const SCOPE_ACCOUNT = 'account';

    /** Try to take one slot. A null limit means unlimited and always succeeds. */
    public function reserve(string $scope, int $scopeId, string $day, ?int $limit): bool
    {
        if ($limit === null) {
            return true;
        }

        if ($limit <= 0) {
            return false;
        }

        if ($this->take($scope, $scopeId, $day, $limit)) {
            return true;
        }

        // Nothing updated: either the day's row does not exist yet, or the limit is reached.
        if ($this->query($scope, $scopeId, $day)->exists()) {
            return false;
        }

        $this->ensureRow($scope, $scopeId, $day);

        return $this->take($scope, $scopeId, $day, $limit);
    }

    /** Give a slot back (the send was abandoned before leaving our server). */
    public function release(string $scope, int $scopeId, string $day, ?int $limit): void
    {
        if ($limit === null) {
            return;
        }

        $this->query($scope, $scopeId, $day)->where('count', '>', 0)->decrement('count', 1, ['updated_at' => now()]);
    }

    public function used(string $scope, int $scopeId, string $day): int
    {
        return (int) $this->query($scope, $scopeId, $day)->value('count');
    }

    private function take(string $scope, int $scopeId, string $day, int $limit): bool
    {
        return $this->query($scope, $scopeId, $day)
            ->where('count', '<', $limit)
            ->increment('count', 1, ['updated_at' => now()]) === 1;
    }

    private function ensureRow(string $scope, int $scopeId, string $day): void
    {
        DB::table('daily_send_counters')->insertOrIgnore([
            'scope' => $scope,
            'scope_id' => $scopeId,
            'day' => $day,
            'count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function query(string $scope, int $scopeId, string $day): Builder
    {
        return DB::table('daily_send_counters')->where([
            'scope' => $scope,
            'scope_id' => $scopeId,
            'day' => $day,
        ]);
    }
}
