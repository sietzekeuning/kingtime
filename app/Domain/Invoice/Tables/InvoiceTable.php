<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Tables;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Shared\Tables\BaseTable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/** @extends BaseTable<Invoice> */
class InvoiceTable extends BaseTable
{
    /** @var array<string> */
    protected array $defaultSort = ['-created_at'];

    protected function allowedSorts(): array
    {
        return ['number', 'status', 'issued_on', 'due_on', 'total', 'created_at'];
    }

    protected function allowedFilters(): array
    {
        return [
            'number',
            AllowedFilter::exact('status'),
            AllowedFilter::exact('client_id'),
            $this->dateFilter('issued_on'),
        ];
    }

    /** @return Builder<Invoice> */
    protected function baseQuery(): Builder
    {
        return Invoice::query()->with('client')->withCount('timeEntries');
    }
}
