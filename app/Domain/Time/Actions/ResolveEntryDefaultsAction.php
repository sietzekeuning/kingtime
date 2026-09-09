<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\Task;

/**
 * What a fresh entry on this project/task inherits: the rate from
 * Project::rateForTask() and the billable flag from the task assignment,
 * falling back to the project's own billability.
 */
class ResolveEntryDefaultsAction
{
    /**
     * @return array{is_billable: bool, hourly_rate: string|null}
     */
    public function handle(Project $project, ?Task $task): array
    {
        $assignment = $task !== null
            ? $project->taskAssignments()->where('task_id', $task->id)->first()
            : null;

        $isBillable = $project->is_billable;

        if ($assignment !== null) {
            $isBillable = $isBillable && $assignment->is_billable;
        } elseif ($task !== null) {
            $isBillable = $isBillable && $task->is_billable_by_default;
        }

        return [
            'is_billable' => $isBillable,
            'hourly_rate' => $project->rateForTask($task),
        ];
    }
}
