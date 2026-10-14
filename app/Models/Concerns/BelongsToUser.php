<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OwnedByUserScope;
use App\Models\User;
use App\Services\UserAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A model whose rows belong to one user.
 *
 *   - reads are filtered by OwnedByUserScope (super admin sees everything)
 *   - a new row is owned by the acting user unless user_id was set explicitly
 *
 * user_id is deliberately not fillable anywhere, so a posted user_id can never
 * move a record to another account.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new OwnedByUserScope);

        static::creating(function (Model $model) {
            if ($model->user_id) {
                return;
            }

            $ownerId = app(UserAccessService::class)->id();

            if (! $ownerId) {
                throw new LogicException(class_basename($model).' was created with no user to own it.');
            }

            $model->user_id = $ownerId;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Only the acting user's own rows - for a super admin too. For things that
     * are personal even to an admin: their mailbox, their notification bell.
     */
    public function scopeMine(Builder $query): Builder
    {
        return $query->withoutGlobalScope(OwnedByUserScope::class)
            ->where($query->qualifyColumn('user_id'), app(UserAccessService::class)->id() ?? 0);
    }
}
