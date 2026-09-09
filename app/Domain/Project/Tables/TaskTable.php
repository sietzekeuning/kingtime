<?php

declare(strict_types=1);

namespace App\Domain\Project\Tables;

use App\Domain\Project\Models\Task;
use App\Domain\Shared\Tables\BaseTable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/** @extends BaseTable<Task> */
class TaskTable extends BaseTable
{
    /** @var array<string> */
    protected array $defaultSort = ['name'];

    protected function allowedSorts(): array
    {
        return ['name', 'is_billable_by_default', 'default_hourly_rate', 'is_active'];
    }

    protected function allowedFilters(): array
    {
        return ['name', AllowedFilter::exact('is_active')];
    }

    /** @return Builder<Task> */
    protected function baseQuery(): Builder
    {
        return Task::query();
    }
}
