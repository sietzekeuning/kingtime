<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\Task;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;

/**
 * Creates a time entry for a user. The rate and billability fall back to what
 * the project (and its task assignment) say when the caller leaves them out,
 * so the web form, the Harvest import and the MCP server all agree.
 */
class LogTimeAction
{
    public function __construct(private ResolveEntryDefaultsAction $resolveDefaults) {}

    public function handle(TimeEntryData $data, User $user): TimeEntry
    {
        $project = Project::query()->findOrFail($data->project_id);
        $task = $data->task_id !== null ? Task::query()->findOrFail($data->task_id) : null;

        $defaults = $this->resolveDefaults->handle($project, $task);

        return TimeEntry::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'spent_on' => $data->spent_on,
            'hours' => $data->hours,
            'notes' => $data->notes,
            'is_billable' => $data->is_billable ?? $defaults['is_billable'],
            'hourly_rate' => $data->hourly_rate ?? $defaults['hourly_rate'],
        ]);
    }
}
