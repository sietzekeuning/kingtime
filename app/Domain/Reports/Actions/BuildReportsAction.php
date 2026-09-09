<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Reports\Data\ReportBreakdownRowData;
use App\Domain\Reports\Data\ReportBucketData;
use App\Domain\Reports\Data\ReportsData;
use App\Domain\Reports\Data\ReportsFilterData;
use App\Domain\Reports\Data\ReportTotalsData;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Builds the reports page for one user and filter: hours and money per
 * week, month or year, totals against the previous range of equal length,
 * and a breakdown per client and per project.
 *
 * The database only aggregates per day (one grouped query for the range,
 * one for the previous range); the days are bucketed into weeks, months or
 * years in PHP so SQLite and MySQL behave the same. Weeks are ISO weeks
 * starting on Monday. Only stored hours count: a running timer's live
 * seconds are left out, as are soft-deleted entries.
 *
 * @phpstan-type DailyTotals array<string, array{hours: float, billable_hours: float, earned: float, invoiced: float, entry_count: int}>
 * @phpstan-type Totals array{hours: float, billable_hours: float, earned: float, invoiced: float, entry_count: int}
 */
final class BuildReportsAction
{
    public const int DEFAULT_MONTHS = 12;

    public const int DEFAULT_WEEKS = 26;

    public const int TOP_PROJECTS = 15;

    public function handle(User $user, ReportsFilterData $filter): ReportsData
    {
        $today = now()->toImmutable()->startOfDay();
        $earliest = $this->earliestEntryOn($user, $filter);
        [$from, $to] = $this->range($filter, $today, $earliest);

        $days = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($days - 1);

        $daily = $this->dailyTotals($user, $filter, $from, $to);
        $previousDaily = $this->dailyTotals($user, $filter, $previousFrom, $previousTo);

        $totals = $this->sum($daily);

        return new ReportsData(
            granularity: $filter->granularity,
            from: $from->toDateString(),
            to: $to->toDateString(),
            previous_from: $previousFrom->toDateString(),
            previous_to: $previousTo->toDateString(),
            earliest_entry_on: $earliest?->toDateString(),
            buckets: $this->buckets($filter->granularity, $from, $to, $daily),
            totals: $this->totals($totals, $this->sum($previousDaily)),
            clients: $this->clientBreakdown($user, $filter, $from, $to, $totals['hours']),
            projects: $this->projectBreakdown($user, $filter, $from, $to, $totals['hours']),
        );
    }

    /**
     * The filter's dates, or the granularity's default range: the last 12
     * months, the last 26 weeks, or every year since the first entry. The
     * range always ends on the last day of the current period so that the
     * previous range covers whole periods too.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(ReportsFilterData $filter, CarbonImmutable $today, ?CarbonImmutable $earliest): array
    {
        $defaultFrom = match ($filter->granularity) {
            'week' => $today->startOfWeek(CarbonInterface::MONDAY)->subWeeks(self::DEFAULT_WEEKS - 1),
            'year' => ($earliest ?? $today)->startOfYear(),
            default => $today->startOfMonth()->subMonthsNoOverflow(self::DEFAULT_MONTHS - 1),
        };

        $defaultTo = match ($filter->granularity) {
            'week' => $today->endOfWeek(CarbonInterface::SUNDAY)->startOfDay(),
            'year' => $today->endOfYear()->startOfDay(),
            default => $today->endOfMonth()->startOfDay(),
        };

        $from = $filter->from !== null ? CarbonImmutable::parse($filter->from)->startOfDay() : $defaultFrom;
        $to = $filter->to !== null ? CarbonImmutable::parse($filter->to)->startOfDay() : $defaultTo;

        if ($to->lt($from)) {
            $to = $from;
        }

        return [$from, $to];
    }

    /**
     * One bucket per period in [$from, $to], oldest first, empty periods
     * included so the chart has no gaps.
     *
     * @param  DailyTotals  $daily
     * @return Collection<int, ReportBucketData>
     */
    private function buckets(string $granularity, CarbonImmutable $from, CarbonImmutable $to, array $daily): Collection
    {
        /** @var array<string, Totals> $sums */
        $sums = [];

        foreach ($daily as $date => $totals) {
            $key = $this->periodKey($granularity, CarbonImmutable::parse($date));
            $sums[$key] = $this->add($sums[$key] ?? $this->zero(), $totals);
        }

        $buckets = [];

        for ($start = $this->periodStart($granularity, $from); $start->lte($to); $start = $this->nextPeriod($granularity, $start)) {
            $key = $this->periodKey($granularity, $start);
            $totals = $sums[$key] ?? $this->zero();

            $buckets[] = new ReportBucketData(
                period: $key,
                label: $this->periodLabel($granularity, $start),
                starts_on: $start->max($from)->toDateString(),
                ends_on: $this->periodEnd($granularity, $start)->min($to)->toDateString(),
                hours: $this->decimal($totals['hours']),
                billable_hours: $this->decimal($totals['billable_hours']),
                earned: $this->decimal($totals['earned']),
                invoiced: $this->decimal($totals['invoiced']),
                entry_count: $totals['entry_count'],
            );
        }

        return collect($buckets);
    }

    /**
     * @param  Totals  $current
     * @param  Totals  $previous
     */
    private function totals(array $current, array $previous): ReportTotalsData
    {
        return new ReportTotalsData(
            hours: $this->decimal($current['hours']),
            billable_hours: $this->decimal($current['billable_hours']),
            earned: $this->decimal($current['earned']),
            invoiced: $this->decimal($current['invoiced']),
            entry_count: $current['entry_count'],
            previous_hours: $this->decimal($previous['hours']),
            previous_billable_hours: $this->decimal($previous['billable_hours']),
            previous_earned: $this->decimal($previous['earned']),
            previous_invoiced: $this->decimal($previous['invoiced']),
            hours_delta_percent: $this->deltaPercent($current['hours'], $previous['hours']),
            billable_hours_delta_percent: $this->deltaPercent($current['billable_hours'], $previous['billable_hours']),
            earned_delta_percent: $this->deltaPercent($current['earned'], $previous['earned']),
            invoiced_delta_percent: $this->deltaPercent($current['invoiced'], $previous['invoiced']),
        );
    }

    /**
     * @return Collection<int, ReportBreakdownRowData>
     */
    private function clientBreakdown(User $user, ReportsFilterData $filter, CarbonImmutable $from, CarbonImmutable $to, float $rangeHours): Collection
    {
        return $this->entriesBetween($user, $filter, $from, $to)
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->groupBy('clients.id', 'clients.name')
            ->selectRaw('clients.id AS id, clients.name AS name, NULL AS client_name, NULL AS color, '.$this->aggregateColumns())
            ->orderByDesc('hours')
            ->orderBy('clients.name')
            ->get()
            ->map(fn (stdClass $row): ReportBreakdownRowData => $this->breakdownRow($row, $rangeHours));
    }

    /**
     * @return Collection<int, ReportBreakdownRowData>
     */
    private function projectBreakdown(User $user, ReportsFilterData $filter, CarbonImmutable $from, CarbonImmutable $to, float $rangeHours): Collection
    {
        return $this->entriesBetween($user, $filter, $from, $to)
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->groupBy('projects.id', 'projects.name', 'projects.color', 'clients.name')
            ->selectRaw('projects.id AS id, projects.name AS name, clients.name AS client_name, projects.color AS color, '.$this->aggregateColumns())
            ->orderByDesc('hours')
            ->orderBy('projects.name')
            ->limit(self::TOP_PROJECTS)
            ->get()
            ->map(fn (stdClass $row): ReportBreakdownRowData => $this->breakdownRow($row, $rangeHours));
    }

    private function breakdownRow(stdClass $row, float $rangeHours): ReportBreakdownRowData
    {
        $hours = (float) $row->hours;

        return new ReportBreakdownRowData(
            id: (int) $row->id,
            name: (string) $row->name,
            client_name: $row->client_name !== null ? (string) $row->client_name : null,
            color: $row->color !== null ? (string) $row->color : null,
            hours: $this->decimal($hours),
            billable_hours: $this->decimal((float) $row->billable_hours),
            earned: $this->decimal((float) $row->earned),
            entry_count: (int) $row->entry_count,
            share_percent: $rangeHours > 0 ? round($hours / $rangeHours * 100, 1) : 0.0,
        );
    }

    /**
     * Hours, billable hours, earned, invoiced and entry count per day in
     * [$from, $to], keyed by `Y-m-d`.
     *
     * @return DailyTotals
     */
    private function dailyTotals(User $user, ReportsFilterData $filter, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->entriesBetween($user, $filter, $from, $to)
            ->groupBy('time_entries.spent_on')
            ->selectRaw('time_entries.spent_on AS spent_on, '.$this->aggregateColumns())
            ->get();

        $daily = [];

        foreach ($rows as $row) {
            $daily[CarbonImmutable::parse((string) $row->spent_on)->toDateString()] = [
                'hours' => (float) $row->hours,
                'billable_hours' => (float) $row->billable_hours,
                'earned' => (float) $row->earned,
                'invoiced' => (float) $row->invoiced,
                'entry_count' => (int) $row->entry_count,
            ];
        }

        return $daily;
    }

    /**
     * Earned is the value of the billable work (hours times rate) whether or
     * not it has been invoiced yet; invoiced is the same sum over entries
     * that are on an invoice.
     *
     * @return literal-string
     */
    private function aggregateColumns(): string
    {
        return <<<'SQL'
            SUM(time_entries.hours) AS hours,
            SUM(CASE WHEN time_entries.is_billable = 1 THEN time_entries.hours ELSE 0 END) AS billable_hours,
            SUM(CASE WHEN time_entries.is_billable = 1 THEN time_entries.hours * COALESCE(time_entries.hourly_rate, 0) ELSE 0 END) AS earned,
            SUM(CASE WHEN time_entries.is_billed = 1 THEN time_entries.hours * COALESCE(time_entries.hourly_rate, 0) ELSE 0 END) AS invoiced,
            COUNT(*) AS entry_count
        SQL;
    }

    private function earliestEntryOn(User $user, ReportsFilterData $filter): ?CarbonImmutable
    {
        $earliest = $this->entries($user, $filter)->min('time_entries.spent_on');

        return $earliest !== null ? CarbonImmutable::parse((string) $earliest)->startOfDay() : null;
    }

    /**
     * Entries in [$from, $to]. The upper bound is exclusive on the next day
     * so it also matches drivers that store the date with a time part.
     */
    private function entriesBetween(User $user, ReportsFilterData $filter, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $this->entries($user, $filter)
            ->where('time_entries.spent_on', '>=', $from->toDateString())
            ->where('time_entries.spent_on', '<', $to->addDay()->toDateString());
    }

    /**
     * The user's live entries joined to their project, narrowed by the
     * client, project and billable filters.
     */
    private function entries(User $user, ReportsFilterData $filter): Builder
    {
        return DB::table('time_entries')
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->where('time_entries.user_id', $user->id)
            ->whereNull('time_entries.deleted_at')
            ->when($filter->client_id !== null, fn (Builder $query) => $query->where('projects.client_id', $filter->client_id))
            ->when($filter->project_id !== null, fn (Builder $query) => $query->where('time_entries.project_id', $filter->project_id))
            ->when($filter->billable_only, fn (Builder $query) => $query->where('time_entries.is_billable', true));
    }

    private function periodStart(string $granularity, CarbonImmutable $date): CarbonImmutable
    {
        return match ($granularity) {
            'week' => $date->startOfWeek(CarbonInterface::MONDAY),
            'year' => $date->startOfYear(),
            default => $date->startOfMonth(),
        };
    }

    private function periodEnd(string $granularity, CarbonImmutable $start): CarbonImmutable
    {
        return match ($granularity) {
            'week' => $start->addDays(6),
            'year' => $start->endOfYear()->startOfDay(),
            default => $start->endOfMonth()->startOfDay(),
        };
    }

    private function nextPeriod(string $granularity, CarbonImmutable $start): CarbonImmutable
    {
        return match ($granularity) {
            'week' => $start->addWeek(),
            'year' => $start->addYear(),
            default => $start->addMonthNoOverflow(),
        };
    }

    private function periodKey(string $granularity, CarbonImmutable $date): string
    {
        return match ($granularity) {
            'week' => $date->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
            'year' => $date->format('Y'),
            default => $date->format('Y-m'),
        };
    }

    private function periodLabel(string $granularity, CarbonImmutable $start): string
    {
        return match ($granularity) {
            'week' => sprintf('Week %d · %d', $start->isoWeek(), $start->isoWeekYear()),
            'year' => $start->format('Y'),
            default => $start->format('M Y'),
        };
    }

    /**
     * @param  DailyTotals  $daily
     * @return Totals
     */
    private function sum(array $daily): array
    {
        $total = $this->zero();

        foreach ($daily as $totals) {
            $total = $this->add($total, $totals);
        }

        return $total;
    }

    /**
     * @param  Totals  $a
     * @param  Totals  $b
     * @return Totals
     */
    private function add(array $a, array $b): array
    {
        return [
            'hours' => $a['hours'] + $b['hours'],
            'billable_hours' => $a['billable_hours'] + $b['billable_hours'],
            'earned' => $a['earned'] + $b['earned'],
            'invoiced' => $a['invoiced'] + $b['invoiced'],
            'entry_count' => $a['entry_count'] + $b['entry_count'],
        ];
    }

    /**
     * @return Totals
     */
    private function zero(): array
    {
        return ['hours' => 0.0, 'billable_hours' => 0.0, 'earned' => 0.0, 'invoiced' => 0.0, 'entry_count' => 0];
    }

    private function deltaPercent(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
