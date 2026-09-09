<?php

declare(strict_types=1);

namespace App\Domain\Project\Actions;

use App\Domain\Project\Data\ProjectTaskData;
use App\Domain\Project\Models\Project;
use Illuminate\Support\Collection;

/**
 * Makes the project's task assignments match the rows submitted by the
 * project form: existing assignments are updated in place (so the Harvest
 * id they were imported with survives), new ones are created, and any
 * assignment that is no longer in the list is removed.
 */
class SyncProjectTasksAction
{
    /**
     * @param  Collection<int, ProjectTaskData>  $assignments
     */
    public function handle(Project $project, Collection $assignments): void
    {
        $taskIds = $assignments->map(fn (ProjectTaskData $assignment) => $assignment->task_id)->all();

        $project->taskAssignments()->whereNotIn('task_id', $taskIds)->delete();

        foreach ($assignments as $assignment) {
            $project->taskAssignments()->updateOrCreate(
                ['task_id' => $assignment->task_id],
                $assignment->toUpdateArray(),
            );
        }
    }
}
