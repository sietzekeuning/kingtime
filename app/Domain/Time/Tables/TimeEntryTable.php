<?php

declare(strict_types=1);

namespace App\Domain\Time\Tables;

use App\Domain\Shared\Tables\BaseTable;
use App\Domain\Time\Models\TimeEntry;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/** @extends BaseTable<TimeEntry> */
class TimeEntryTable extends BaseTable
{
    /** @var array<string> */
    protected array $defaultSort = ['-spent_on', '-id'];

    protected function allowedSorts(): array
    {
        return ['spent_on', 'hours', 'is_billable', 'is_billed'];
    }

    protected function allowedFilters(): array
    {
        return [
            'notes',
            $this->dateFilter('spent_on'),
            AllowedFilter::exact('project_id'),
            AllowedFilter::exact('is_billable'),
            AllowedFilter::exact('is_billed'),
            AllowedFilter::callback('client_id', static fn (Builder $query, mixed $value) => $query->whereHas(
                'project',
                static fn (Builder $project) => $project->where('client_id', $value),
            )),
            AllowedFilter::callback('from', static fn (Builder $query, mixed $value) => $query->whereDate('spent_on', '>=', $value)),
            AllowedFilter::callback('to', static fn (Builder $query, mixed $value) => $query->whereDate('spent_on', '<=', $value)),
        ];
    }

    /** @return Builder<TimeEntry> */
    protected function baseQuery(): Builder
    {
        return TimeEntry::query()->with(['project.client', 'user']);
    }
}
