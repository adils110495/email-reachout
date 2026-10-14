<?php

namespace App\Jobs\Middleware;

use App\Models\User;
use App\Services\UserAccessService;
use Closure;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Runs a queued job as the user who owns its data.
 *
 * A worker has no signed-in user, so without this every owned query in the job
 * would match nothing (the scope fails closed) and every create would have no
 * owner. With it, the job sees exactly its owner's rows - never another user's,
 * even when a super admin queued it.
 */
class ActAsOwner
{
    public function __construct(private readonly ?int $userId) {}

    public function handle(object $job, Closure $next): mixed
    {
        if (! $this->userId) {
            // Queued before jobs carried an owner. Running it as nobody would
            // quietly do nothing, so fail it where it shows up instead.
            $job->fail(new RuntimeException(class_basename($job).' has no owning user - please start it again.'));

            return null;
        }

        $owner = User::find($this->userId);

        if (! $owner) {
            // The account was deleted, and its data with it.
            Log::info(class_basename($job).': owner no longer exists, job dropped', ['user_id' => $this->userId]);
            $job->delete();

            return null;
        }

        return app(UserAccessService::class)->runAs($owner, fn () => $next($job));
    }
}
