<?php

declare(strict_types=1);

namespace App\Domain\Project\Tables;

use App\Domain\Project\Models\Project;
use App\Domain\Shared\Tables\BaseTable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/** @extends BaseTable<Project> */
class ProjectTable extends BaseTable
{
    /** @var array<string> */
    protected array $defaultSort = ['-is_active', 'name'];

    protected function allowedSorts(): array
    {
        return ['name', 'code', 'is_active', 'is_billable', 'hourly_rate', 'total_hours'];
    }

    protected function allowedFilters(): array
    {
        return [
            'name',
            'code',
            AllowedFilter::exact('client_id'),
            $this->archivedFilter(),
            AllowedFilter::exact('is_billable'),
        ];
    }

    /** @return Builder<Project> */
    protected function baseQuery(): Builder
    {
        return Project::query()
            ->when($this->hidesArchived(), fn (Builder $query) => $query->where('is_active', true))
            ->with('client')
            ->withSum('timeEntries as total_hours', 'hours')
            ->withSum(['timeEntries as unbilled_hours' => fn ($query) => $query->unbilled()], 'hours');
    }
}
