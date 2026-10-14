<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\ActAsOwner;
use App\Services\UserAccessService;

/**
 * For jobs that work on one user's data: handle() runs as that user through
 * the ActAsOwner middleware.
 *
 * failed() is not passed through job middleware, so it wraps its own writes
 * in asOwner().
 */
trait RunsAsOwner
{
    /** The user whose data this job reads and writes. */
    abstract public function ownerId(): ?int;

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new ActAsOwner($this->ownerId())];
    }

    protected function asOwner(callable $callback): mixed
    {
        $ownerId = $this->ownerId();

        return $ownerId
            ? app(UserAccessService::class)->runAs($ownerId, $callback)
            : $callback();
    }
}
