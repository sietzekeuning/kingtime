<?php

declare(strict_types=1);

namespace App\Domain\Shared\Tables;

use App\Domain\Shared\Pagination\TablePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
abstract class BaseTable
{
    /**
     * @var array<string>
     */
    protected array $defaultSort = [];

    /**
     * @var array<string, mixed>
     */
    private array $params = [];

    /** @var Builder<TModel>|null */
    private ?Builder $customBaseQuery = null;

    /**
     * @return array<string|AllowedSort>
     */
    abstract protected function allowedSorts(): array;

    /**
     * @return array<string|AllowedFilter>
     */
    abstract protected function allowedFilters(): array;

    /** @return Builder<TModel> */
    abstract protected function baseQuery(): Builder;

    /** @param Builder<TModel> $query */
    public function setBaseQuery(Builder $query): void
    {
        $this->customBaseQuery = $query;
    }

    /**
     * @return TablePaginator<int, TModel>
     */
    public function getData(Request $request, ?int $perPage = null): TablePaginator
    {
        $perPage ??= (int) $request->query('perPage', '25');

        $queryBuilder = $this->getQueryBuilder();

        $page = $queryBuilder->paginate($perPage);

        $paginator = new TablePaginator($page->items(), $page->total(), $perPage, $page->currentPage(), [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $paginator->allowedSorts = $this->allowedSorts();
        $paginator->allowedFilters = $this->allowedFilters();

        return $paginator;
    }

    public function getParam(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    public function setParam(string $key, mixed $param): void
    {
        $this->params[$key] = $param;
    }

    /**
     * The archive filter every list with an `is_active` column uses: the
     * list shows active rows unless the filter is set, "0" shows the
     * archived ones and "all" shows both.
     */
    protected function archivedFilter(string $column = 'is_active'): AllowedFilter
    {
        return AllowedFilter::callback('is_active', static function (Builder $query, mixed $value) use ($column): void {
            if ($value === 'all') {
                return;
            }

            $query->where($column, filter_var($value, FILTER_VALIDATE_BOOLEAN));
        });
    }

    /**
     * Whether the base query should hide archived rows, which is the case
     * as long as the request carries no explicit `filter[is_active]`.
     */
    protected function hidesArchived(): bool
    {
        return ! request()->filled('filter.is_active');
    }

    protected function globalSearchFilter(?string $table = null, string $titleColumn = 'name'): AllowedFilter
    {
        $tablePrefix = $table ? $table.'.' : '';

        return AllowedFilter::callback('global', static fn ($query, $value) => $query->where(static fn ($q) => $q->where(
            $tablePrefix.$titleColumn,
            'LIKE',
            "%{$value}%",
        )));
    }

    /**
     * Filters a date column on a partially typed date, the way an admin types
     * one: "2026", "09-2026", "17/09/2026" and the ISO "2026-09-17" all narrow
     * the list. Deliberately built from whereYear/whereMonth/whereDay instead
     * of a formatted string comparison, so it behaves the same on MySQL and on
     * the SQLite the test suite runs on.
     */
    protected function dateFilter(string $name, ?string $column = null): AllowedFilter
    {
        $column ??= $name;

        return AllowedFilter::callback($name, static function (Builder $query, mixed $value) use ($column): void {
            /** @var array<int, string> $parts */
            $parts = preg_split('/\D+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($parts === []) {
                return;
            }

            $year = null;
            $month = null;
            $day = null;

            if (count($parts) === 1) {
                if (mb_strlen($parts[0]) === 4) {
                    $year = (int) $parts[0];
                } else {
                    $day = (int) $parts[0];
                }
            } elseif (count($parts) === 2) {
                [$first, $second] = $parts;
                if (mb_strlen($first) === 4) {
                    $year = (int) $first;
                    $month = (int) $second;
                } elseif (mb_strlen($second) === 4 || (int) $second > 12) {
                    $year = self::expandYear((int) $second);
                    $month = (int) $first;
                } else {
                    $day = (int) $first;
                    $month = (int) $second;
                }
            } else {
                [$first, $second, $third] = array_slice($parts, 0, 3);
                if (mb_strlen($first) === 4) {
                    $year = (int) $first;
                    $month = (int) $second;
                    $day = (int) $third;
                } else {
                    $day = (int) $first;
                    $month = (int) $second;
                    $year = self::expandYear((int) $third);
                }
            }

            if ($month !== null && ($month < 1 || $month > 12) || $day !== null && ($day < 1 || $day > 31)) {
                $query->whereRaw('1 = 0');

                return;
            }

            if ($year !== null) {
                $query->whereYear($column, $year);
            }
            if ($month !== null) {
                $query->whereMonth($column, $month);
            }
            if ($day !== null) {
                $query->whereDay($column, $day);
            }
        });
    }

    /**
     * Filters a numeric column on an exact value, or on a comparison when the
     * admin prefixes it: "500", "> 500", "<=100".
     */
    protected function numericFilter(string $name, ?string $column = null): AllowedFilter
    {
        $column ??= $name;

        return AllowedFilter::callback($name, static function (Builder $query, mixed $value) use ($column): void {
            [$operator, $number] = self::parseComparison($value);

            if ($number === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($column, $operator, $number);
        });
    }

    /**
     * Splits "> 500" into ['>', 500.0]. Returns a null number when nothing
     * numeric was typed, so the caller can decide what an unparsable filter
     * means.
     *
     * @return array{0: string, 1: float|null}
     */
    protected static function parseComparison(mixed $value): array
    {
        // The query builder splits "8,50" on the comma into ['8', '50'];
        // rejoin it, because the comma is a Dutch decimal separator here.
        $typed = is_array($value) ? implode(',', $value) : (string) $value;
        $raw = str_replace([' ', ',', '€'], ['', '.', ''], trim($typed));

        $operator = '=';
        foreach (['>=', '<=', '>', '<', '='] as $candidate) {
            if (str_starts_with($raw, $candidate)) {
                $operator = $candidate;
                $raw = mb_substr($raw, mb_strlen($candidate));

                break;
            }
        }

        return [$operator, is_numeric($raw) ? (float) $raw : null];
    }

    /**
     * Reads a two-digit year the way the admin means it: 26 is 2026.
     */
    private static function expandYear(int $year): int
    {
        return $year < 100 ? 2000 + $year : $year;
    }

    /**
     * @return QueryBuilder<TModel>
     */
    public function getQueryBuilder(): QueryBuilder
    {
        $baseQuery = $this->customBaseQuery ?? $this->baseQuery();

        /** @var QueryBuilder<TModel> $queryBuilder */
        $queryBuilder = QueryBuilder::for($baseQuery)->allowedSorts(...$this->allowedSorts())->allowedFilters(
            ...$this->allowedFilters(),
        );

        if (! request()->has('sort') && ! empty($this->defaultSort)) {
            $queryBuilder->defaultSort(...$this->defaultSort);
        }

        return $queryBuilder;
    }

    /**
     * @return array<string|AllowedSort>
     */
    public function getAllowedSorts(): array
    {
        return $this->allowedSorts();
    }

    /**
     * @return array<string|AllowedFilter>
     */
    public function getAllowedFilters(): array
    {
        return $this->allowedFilters();
    }
}
