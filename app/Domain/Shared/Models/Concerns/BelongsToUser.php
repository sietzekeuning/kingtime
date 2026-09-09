<?php

declare(strict_types=1);

namespace App\Domain\Shared\Models\Concerns;

use App\Domain\Shared\Models\Scopes\UserScope;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data that belongs to one user and is invisible to every other user:
 * clients, projects, time entries, invoices and import runs.
 *
 * Queries are narrowed to the authenticated user by {@see UserScope}, and a
 * new row takes the authenticated user as its owner when the caller did not
 * set one. Code that runs without an authenticated user (jobs, commands)
 * must pass `user_id` explicitly and query through {@see self::ownedBy()}.
 *
 * @mixin Model
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new UserScope);

        static::creating(function (Model $model): void {
            /** @var Model&self $model */
            if ($model->getAttribute('user_id') === null) {
                $model->setAttribute('user_id', $model->resolveOwnerId());
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The rows of one user, whoever is (not) authenticated. For jobs and
     * commands that act on behalf of a user.
     *
     * @return Builder<static>
     */
    public static function ownedBy(User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;
        $query = static::query()->withoutGlobalScope(UserScope::class);

        return $query->where($query->getModel()->qualifyColumn('user_id'), $userId);
    }

    /**
     * The owner of a row created without an explicit `user_id`. Models whose
     * owner follows from a parent (a project takes its client's owner)
     * override this.
     */
    protected function resolveOwnerId(): ?int
    {
        $user = auth()->user();

        return $user instanceof User ? $user->id : null;
    }
}
