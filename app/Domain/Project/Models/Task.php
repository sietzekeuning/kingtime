<?php

declare(strict_types=1);

namespace App\Domain\Project\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_billable_by_default
 * @property string|null $default_hourly_rate
 * @property bool $is_active
 * @property int|null $harvest_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_billable_by_default' => 'boolean',
            'is_active' => 'boolean',
            'default_hourly_rate' => 'decimal:2',
        ];
    }

    /** @return BelongsToMany<Project, $this, ProjectTask> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->using(ProjectTask::class)
            ->withPivot(['id', 'is_billable', 'hourly_rate', 'is_active', 'harvest_id'])
            ->withTimestamps();
    }
}
