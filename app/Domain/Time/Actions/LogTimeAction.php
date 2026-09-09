<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Project\Models\Project;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;

/**
 * Creates a time entry for a user. The rate and billability fall back to what
 * the project says when the caller leaves them out, so the web form, the
 * Harvest import and the MCP server all agree.
 */
class LogTimeAction
{
    public function __construct(private ResolveEntryDefaultsAction $resolveDefaults) {}

    public function handle(TimeEntryData $data, User $user): TimeEntry
    {
        $project = Project::query()->findOrFail($data->project_id);

        $defaults = $this->resolveDefaults->handle($project);

        return TimeEntry::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'spent_on' => $data->spent_on,
            'hours' => $data->hours,
            'notes' => $data->notes,
            'is_billable' => $data->is_billable ?? $defaults['is_billable'],
            'hourly_rate' => $data->hourly_rate ?? $defaults['hourly_rate'],
        ]);
    }
}
