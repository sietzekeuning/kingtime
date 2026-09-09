<?php

declare(strict_types=1);

namespace App\Domain\Project\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A task assigned to a project (Harvest calls this a task assignment). It
 * carries the per-project override of billability and rate.
 *
 * @property int $id
 * @property int $project_id
 * @property int $task_id
 * @property bool $is_billable
 * @property string|null $hourly_rate
 * @property bool $is_active
 * @property int|null $harvest_id
 */
class ProjectTask extends Pivot
{
    public $incrementing = true;

    protected $table = 'project_task';

    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
            'is_active' => 'boolean',
            'hourly_rate' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
