<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Project\Models\Project;
use App\Domain\Time\Data\ProjectOptionData;
use Illuminate\Support\Collection;

/**
 * Active projects with the tasks assigned to them, as the project and task
 * selects of the time entry form need them.
 */
class ListProjectOptionsAction
{
    /**
     * @return Collection<int, ProjectOptionData>
     */
    public function handle(): Collection
    {
        return Project::query()
            ->where('is_active', true)
            ->with(['client', 'taskAssignments.task'])
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => ProjectOptionData::fromModel($project));
    }
}
