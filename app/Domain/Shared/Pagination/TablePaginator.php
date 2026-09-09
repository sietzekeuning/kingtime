<?php

declare(strict_types=1);

namespace App\Domain\Shared\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @template TKey of array-key
 * @template TValue of mixed
 *
 * @extends LengthAwarePaginator<TKey, TValue>
 */
class TablePaginator extends LengthAwarePaginator
{
    /**
     * @var array<string|AllowedSort>
     */
    public array $allowedSorts = [];

    /**
     * @var array<string|AllowedFilter>
     */
    public array $allowedFilters = [];

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $filters = array_map(static function ($filter) {
            if ($filter instanceof AllowedFilter) {
                return ['name' => $filter->getName()];
            }

            return $filter;
        }, $this->allowedFilters);

        // The frontend matches a column's `show` key against this list to
        // decide whether it renders a sort control, so a callback sort has to
        // flatten back to its plain name.
        $sorts = array_map(static fn ($sort) => $sort instanceof AllowedSort
            ? $sort->getName()
            : $sort, $this->allowedSorts);

        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'links' => $this->linkCollection()->toArray(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
            'total' => $this->total(),
            'allowed_sorts' => $sorts,
            'allowed_filters' => $filters,
        ];
    }
}
