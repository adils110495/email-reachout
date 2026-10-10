<?php

namespace App\Sequencer\Services;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Per-account sending speed ("10 emails/minute") shared by every worker.
 *
 * One atomic counter per account per clock minute in the shared cache (Redis in
 * production). INCR is atomic, so concurrent workers cannot both slip under the limit.
 */
class SendRateLimiter
{
    /**
     * Try to take one send slot for this minute.
     *
     * @return int 0 when the send may proceed, otherwise seconds until the next minute
     */
    public function acquire(MailSetting $account): int
    {
        $limit = (int) $account->rate_limit_per_minute;
        if ($limit <= 0) {
            return 0;   // 0 = no per-minute cap
        }

        $now = now();
        $key = sprintf('sequencer:rate:%d:%s', $account->id, $now->format('YmdHi'));

        Cache::add($key, 0, 120);
        $count = (int) Cache::increment($key);

        return $count > $limit ? max(1, 60 - $now->second) : 0;
    }
}
