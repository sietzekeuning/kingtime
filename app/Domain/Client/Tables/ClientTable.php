<?php

declare(strict_types=1);

namespace App\Domain\Client\Tables;

use App\Domain\Client\Models\Client;
use App\Domain\Shared\Tables\BaseTable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/** @extends BaseTable<Client> */
class ClientTable extends BaseTable
{
    /** @var array<string> */
    protected array $defaultSort = ['name'];

    protected function allowedSorts(): array
    {
        return ['name', 'email', 'is_active', 'projects_count'];
    }

    protected function allowedFilters(): array
    {
        return ['name', 'email', AllowedFilter::exact('is_active')];
    }

    /** @return Builder<Client> */
    protected function baseQuery(): Builder
    {
        return Client::query()->withCount('projects');
    }
}
