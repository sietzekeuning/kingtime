<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Models;

use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\User\Models\User;
use Database\Factories\HarvestImportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One run of the Harvest import, successful or not.
 *
 * @property int $id
 * @property int|null $user_id
 * @property HarvestImportStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $updated_since
 * @property array<string, array{created: int, updated: int}>|null $counts
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class HarvestImport extends Model
{
    /** @use HasFactory<HarvestImportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => HarvestImportStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'updated_since' => 'datetime',
            'counts' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<HarvestImport>  $query
     * @return Builder<HarvestImport>
     */
    public function scopeFinished(Builder $query): Builder
    {
        return $query->where('status', HarvestImportStatus::Finished);
    }

    public static function lastSuccessfulFor(User $user): ?self
    {
        return self::query()->where('user_id', $user->id)->finished()->latest('started_at')->first();
    }
}
