<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\Task;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\Time\Models\TimeEntry;

/**
 * Edits the user-facing fields of an entry. Rate and billability are kept
 * unless the caller sets them or moves the entry to another project/task, in
 * which case they are derived again from the new assignment.
 */
class UpdateTimeEntryAction
{
    public function __construct(private ResolveEntryDefaultsAction $resolveDefaults) {}

    public function handle(TimeEntry $entry, TimeEntryData $data): TimeEntry
    {
        if ($entry->isLocked()) {
            throw TimeEntryLockedException::for($entry);
        }

        $project = Project::query()->findOrFail($data->project_id);
        $task = $data->task_id !== null ? Task::query()->findOrFail($data->task_id) : null;

        $assignmentChanged = $project->id !== $entry->project_id || $task?->id !== $entry->task_id;

        $isBillable = $entry->is_billable;
        $hourlyRate = $entry->hourly_rate;

        if ($assignmentChanged) {
            $defaults = $this->resolveDefaults->handle($project, $task);
            $isBillable = $defaults['is_billable'];
            $hourlyRate = $defaults['hourly_rate'];
        }

        $entry->update([
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'spent_on' => $data->spent_on,
            'hours' => $data->hours,
            'notes' => $data->notes,
            'is_billable' => $data->is_billable ?? $isBillable,
            'hourly_rate' => $data->hourly_rate ?? $hourlyRate,
        ]);

        return $entry;
    }
}
