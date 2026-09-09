<?php

declare(strict_types=1);

namespace App\Domain\Project\Models;

use App\Domain\Client\Models\Client;
use App\Domain\Project\Enums\BillBy;
use App\Domain\Time\Models\TimeEntry;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string|null $code
 * @property bool $is_billable
 * @property BillBy $bill_by
 * @property string|null $hourly_rate
 * @property string|null $budget_hours
 * @property bool $is_active
 * @property string|null $color
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $notes
 * @property int|null $harvest_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Client $client
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
            'is_active' => 'boolean',
            'bill_by' => BillBy::class,
            'hourly_rate' => 'decimal:2',
            'budget_hours' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsToMany<Task, $this, ProjectTask> */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->using(ProjectTask::class)
            ->withPivot(['id', 'is_billable', 'hourly_rate', 'is_active', 'harvest_id'])
            ->withTimestamps();
    }

    /** @return HasMany<ProjectTask, $this> */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * The rate a new entry on this project/task should be billed at. Task
     * assignments override the project rate when the project bills by task.
     */
    public function rateForTask(?Task $task): ?string
    {
        if (! $this->is_billable || $this->bill_by === BillBy::None) {
            return null;
        }

        if ($this->bill_by === BillBy::Task && $task !== null) {
            $assignment = $this->taskAssignments()->where('task_id', $task->id)->first();

            if ($assignment !== null && $assignment->hourly_rate !== null) {
                return $assignment->hourly_rate;
            }

            return $task->default_hourly_rate;
        }

        return $this->hourly_rate;
    }
}
