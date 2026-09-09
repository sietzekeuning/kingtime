<?php

declare(strict_types=1);

namespace App\Domain\Shared\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Every tenant-owned model only ever shows the rows of the authenticated
 * user. Without an authenticated user (queue workers, the scheduler, artisan
 * commands) nothing is filtered, so that code must scope by `user_id`
 * itself.
 *
 * @implements Scope<Model>
 */
class UserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $userId = auth()->id();

        if ($userId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('user_id'), $userId);
    }
}
