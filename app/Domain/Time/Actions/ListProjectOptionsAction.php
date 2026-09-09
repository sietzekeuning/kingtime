<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Project\Models\Project;
use App\Domain\Time\Data\ProjectOptionData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Active projects as the project select of the time entry form needs them.
 * An entry that already belongs to an archived project keeps that project
 * in the list, so editing it does not silently move the hours elsewhere.
 */
class ListProjectOptionsAction
{
    /**
     * @return Collection<int, ProjectOptionData>
     */
    public function handle(?int $includeProjectId = null): Collection
    {
        return Project::query()
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->when($includeProjectId !== null, fn (Builder $query) => $query->orWhere('id', $includeProjectId)))
            ->with('client')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => ProjectOptionData::fromModel($project));
    }
}
