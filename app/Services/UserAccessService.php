<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use RuntimeException;

/**
 * Whose data is being read or written right now?
 *
 * Every owned model's global scope (OwnedByUserScope) asks this one class, so
 * the access rule lives here and nowhere else:
 *
 *   web request, normal user   only their own rows
 *   web request, super admin   every row, unfiltered
 *   runAs($user)               strictly that user's rows - even when they are a
 *                              super admin. Queued jobs run this way, and so do
 *                              actions a super admin takes on someone else's
 *                              record, so the work is filed under its owner.
 *   asSystem()                 every row. Explicit system-wide work only (the
 *                              scheduler, the open-tracking pixel).
 *   none of the above          no rows at all - fail closed.
 *
 * Registered as a scoped singleton, so a queue worker gets a clean instance for
 * every job and nothing leaks from one job into the next.
 */
class UserAccessService
{
    private ?User $actingAs = null;

    private bool $system = false;

    /** The user data is being handled for: runAs() first, then the signed-in user. */
    public function user(): ?User
    {
        if ($this->system) {
            return null;
        }

        return $this->actingAs ?? Auth::user();
    }

    public function id(): ?int
    {
        return $this->user()?->id;
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    /** True when queries must not be filtered by owner at all. */
    public function bypassesScope(): bool
    {
        if ($this->system) {
            return true;
        }

        if ($this->actingAs) {
            return false;
        }

        return (bool) Auth::user()?->isSuperAdmin();
    }

    /**
     * Run $callback strictly as $user: their rows only, and anything created
     * is owned by them.
     */
    public function runAs(User|int|null $user, callable $callback): mixed
    {
        $user = is_int($user) ? User::find($user) : $user;

        if (! $user) {
            throw new RuntimeException('Cannot act as a user that does not exist.');
        }

        return $this->swap($user, false, $callback);
    }

    /** Run $callback with no owner filter. Only for genuine system-wide work. */
    public function asSystem(callable $callback): mixed
    {
        return $this->swap(null, true, $callback);
    }

    /**
     * An `exists` rule limited to rows the acting user may use, so a posted
     * category_id / platform_id / address_id cannot point at someone else's.
     */
    public function existsRule(string $table, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column);

        return $this->bypassesScope() ? $rule : $rule->where('user_id', $this->id() ?? 0);
    }

    /** A `unique` rule per owner: two users may both have a "Restaurants" category. */
    public function uniqueRule(string $table, string $column, ?int $ownerId = null): Unique
    {
        return Rule::unique($table, $column)->where('user_id', $ownerId ?? $this->id() ?? 0);
    }

    private function swap(?User $user, bool $system, callable $callback): mixed
    {
        [$previousUser, $previousSystem] = [$this->actingAs, $this->system];

        $this->actingAs = $user;
        $this->system   = $system;

        try {
            return $callback();
        } finally {
            $this->actingAs = $previousUser;
            $this->system   = $previousSystem;
        }
    }
}
