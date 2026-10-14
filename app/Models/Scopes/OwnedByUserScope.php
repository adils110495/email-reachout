<?php

namespace App\Models\Scopes;

use App\Services\UserAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query on an owned model to the acting user's rows.
 *
 * Because it is a global scope, findOrFail(), exports, bulk actions, AJAX
 * endpoints and whereHas() sub-queries are all covered without a check in each
 * controller - changing /leads/5 to /leads/6 simply finds nothing.
 */
class OwnedByUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $access = app(UserAccessService::class);

        if ($access->bypassesScope()) {
            return;
        }

        // No acting user (a console command or job that never said whose data
        // it is) matches user_id 0, which is no row at all.
        $builder->where($model->qualifyColumn('user_id'), $access->id() ?? 0);
    }
}
