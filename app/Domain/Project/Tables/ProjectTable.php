<?php

declare(strict_types=1);

namespace App\Domain\Project\Tables;

use App\Domain\Project\Models\Project;
use App\Domain\Shared\Tables\BaseTable;
use App\Domain\Time\Models\TimeEntry;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/** @extends BaseTable<Project> */
class ProjectTable extends BaseTable
{
    /** @var array<string> */
    protected array $defaultSort = ['-is_active', 'name'];

    protected function allowedSorts(): array
    {
        return ['name', 'code', 'is_active', 'is_billable', 'hourly_rate', 'total_hours', 'budget_amount', 'spent_amount'];
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
            ->withSum(['timeEntries as unbilled_hours' => fn ($query) => $query->unbilled()], 'hours')
            ->addSelect(['spent_amount' => $this->spentAmountQuery()]);
    }

    /**
     * What the logged hours are worth at the rate each entry was logged at,
     * the "spent" side of a money budget. Entries without a rate count for
     * nothing.
     *
     * @return Builder<TimeEntry>
     */
    private function spentAmountQuery(): Builder
    {
        return TimeEntry::query()
            ->selectRaw('COALESCE(SUM(hours * COALESCE(hourly_rate, 0)), 0)')
            ->whereColumn('time_entries.project_id', 'projects.id');
    }
}
