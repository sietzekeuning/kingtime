<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Actions;

use App\Domain\Dashboard\Data\DashboardChartData;
use App\Domain\Dashboard\Data\DashboardData;
use App\Domain\Dashboard\Data\DashboardProjectSummaryData;
use App\Domain\Dashboard\Data\DashboardStatData;
use App\Domain\Dashboard\Data\DashboardStatisticsData;
use App\Domain\Dashboard\Data\DashboardStatsData;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds every section of the dashboard for one user in a handful of
 * grouped queries: per-day totals for the stat tiles and statistics, per-day
 * totals over the chart range, one grouped query for the top projects, and
 * the six most recent entries.
 *
 * Weeks start on Monday. A running timer's elapsed time is added to its day
 * so the tiles and chart show what the user is at right now.
 *
 * @phpstan-type DailyTotals array<string, array{hours: float, billable_hours: float, billable_amount: float}>
 */
final class BuildDashboardAction
{
    public const string DEFAULT_RANGE = '6m';

    public const int RECENT_ENTRIES = 6;

    public const int TOP_PROJECTS = 6;

    /** Weeks per chart range, the current (partial) week included. */
    private const array RANGE_WEEKS = ['3m' => 13, '6m' => 26, '1y' => 52];

    public function handle(User $user, string $range = self::DEFAULT_RANGE): DashboardData
    {
        $range = $this->normalizeRange($range);
        $today = now()->toImmutable()->startOfDay();
        $running = $this->runningTimer($user);

        $statsFrom = $today->subMonthNoOverflow()->startOfMonth();
        $daily = $this->dailyTotals($user, $statsFrom, $today, $running);

        return new DashboardData(
            stats: $this->stats($daily, $today),
            chart: $this->chart($user, $today, $range, $running),
            statistics: $this->statistics($daily, $today, $running),
            recent_entries: $this->recentEntries($user),
            top_projects: $this->topProjects($user, $today, $daily, $running),
        );
    }

    /**
     * @return '3m'|'6m'|'1y'
     */
    private function normalizeRange(string $range): string
    {
        return match ($range) {
            '3m', '1y' => $range,
            default => self::DEFAULT_RANGE,
        };
    }

    private function runningTimer(User $user): ?TimeEntry
    {
        return TimeEntry::query()
            ->where('user_id', $user->id)
            ->where('is_running', true)
            ->with(['project.client', 'task'])
            ->latest('timer_started_at')
            ->first();
    }

    /**
     * @param  DailyTotals  $daily
     */
    private function stats(array $daily, CarbonImmutable $today): DashboardStatsData
    {
        $yesterday = $today->subDay();
        $weekStart = $today->startOfWeek(CarbonInterface::MONDAY);
        $lastWeekStart = $weekStart->subWeek();
        $lastWeekEnd = $weekStart->subDay();
        $monthStart = $today->startOfMonth();
        $lastMonthStart = $monthStart->subMonthNoOverflow();
        $lastMonthEnd = $monthStart->subDay();

        return new DashboardStatsData(items: collect([
            $this->stat(
                'Hours today',
                'hours',
                $this->sum($daily, 'hours', $today, $today),
                $this->sum($daily, 'hours', $yesterday, $yesterday),
                'Today',
                'Yesterday',
            ),
            $this->stat(
                'Hours this week',
                'hours',
                $this->sum($daily, 'hours', $weekStart, $today),
                $this->sum($daily, 'hours', $lastWeekStart, $lastWeekEnd),
                'This week',
                'Last week',
            ),
            $this->stat(
                'Hours this month',
                'hours',
                $this->sum($daily, 'hours', $monthStart, $today),
                $this->sum($daily, 'hours', $lastMonthStart, $lastMonthEnd),
                'This month',
                'Last month',
            ),
            $this->stat(
                'Billable this month',
                'euro',
                $this->sum($daily, 'billable_amount', $monthStart, $today),
                $this->sum($daily, 'billable_amount', $lastMonthStart, $lastMonthEnd),
                'This month',
                'Last month',
            ),
        ]));
    }

    /**
     * @param  'hours'|'euro'  $format
     */
    private function stat(
        string $label,
        string $format,
        float $current,
        float $previous,
        string $currentLabel,
        string $previousLabel,
    ): DashboardStatData {
        return new DashboardStatData(
            label: $label,
            value: $this->decimal($current),
            previous_value: $this->decimal($previous),
            delta_percent: $this->deltaPercent($current, $previous),
            current_label: $currentLabel,
            previous_label: $previousLabel,
            format: $format,
        );
    }

    /**
     * @param  '3m'|'6m'|'1y'  $range
     */
    private function chart(User $user, CarbonImmutable $today, string $range, ?TimeEntry $running): DashboardChartData
    {
        $currentWeek = $today->startOfWeek(CarbonInterface::MONDAY);
        $firstWeek = $currentWeek->subWeeks(self::RANGE_WEEKS[$range] - 1);
        $daily = $this->dailyTotals($user, $firstWeek, $today, $running);

        $buckets = [];
        for ($week = $firstWeek; $week->lte($currentWeek); $week = $week->addWeek()) {
            $buckets[$week->toDateString()] = 0.0;
        }

        foreach ($daily as $date => $totals) {
            $week = CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY)->toDateString();
            $buckets[$week] = ($buckets[$week] ?? 0.0) + $totals['hours'];
        }

        return new DashboardChartData(
            range: $range,
            labels: array_map(fn (string $week): string => CarbonImmutable::parse($week)->isoFormat('MMM D'), array_keys($buckets)),
            values: array_map(fn (float $hours): float => round($hours, 2), array_values($buckets)),
        );
    }

    /**
     * @param  DailyTotals  $daily
     */
    private function statistics(array $daily, CarbonImmutable $today, ?TimeEntry $running): DashboardStatisticsData
    {
        $monthStart = $today->startOfMonth();
        $lastMonthStart = $monthStart->subMonthNoOverflow();
        $lastMonthEnd = $monthStart->subDay();

        $ratio = $this->billableRatio($daily, $monthStart, $today);
        $previousRatio = $this->billableRatio($daily, $lastMonthStart, $lastMonthEnd);

        $workingDays = max(1, CarbonPeriod::create($monthStart, $today)->filter('isWeekday')->count());
        $hoursThisMonth = $this->sum($daily, 'hours', $monthStart, $today);

        return new DashboardStatisticsData(
            billable_ratio: $ratio,
            billable_ratio_previous: $previousRatio,
            billable_ratio_delta: $ratio !== null && $previousRatio !== null ? round($ratio - $previousRatio, 1) : null,
            average_hours_per_working_day: $this->decimal($hoursThisMonth / $workingDays),
            working_days_this_month: $workingDays,
            running_timer: $running !== null ? TimeEntryData::fromModel($running) : null,
        );
    }

    /**
     * @return Collection<int, TimeEntryData>
     */
    private function recentEntries(User $user): Collection
    {
        return TimeEntry::query()
            ->where('user_id', $user->id)
            ->with(['project.client', 'task'])
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->limit(self::RECENT_ENTRIES)
            ->get()
            ->map(fn (TimeEntry $entry): TimeEntryData => TimeEntryData::fromModel($entry));
    }

    /**
     * @param  DailyTotals  $daily
     * @return Collection<int, DashboardProjectSummaryData>
     */
    private function topProjects(User $user, CarbonImmutable $today, array $daily, ?TimeEntry $running): Collection
    {
        $monthStart = $today->startOfMonth();
        $monthHours = $this->sum($daily, 'hours', $monthStart, $today);

        $runningProjectId = $running !== null && $running->spent_on->between($monthStart, $today) ? $running->project_id : null;
        $runningElapsed = $running !== null ? $this->elapsedHours($running) : 0.0;

        return $this->entriesBetween($user, $monthStart, $today)
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->groupBy('projects.id', 'projects.name', 'projects.color', 'clients.name')
            ->selectRaw(<<<'SQL'
                projects.id AS project_id,
                projects.name AS project_name,
                projects.color AS project_color,
                clients.name AS client_name,
                SUM(time_entries.hours) AS hours
            SQL)
            ->orderByDesc('hours')
            ->orderBy('projects.name')
            ->limit(self::TOP_PROJECTS)
            ->get()
            ->map(function (object $row) use ($monthHours, $runningProjectId, $runningElapsed): DashboardProjectSummaryData {
                $projectId = (int) $row->project_id;
                $hours = (float) $row->hours + ($projectId === $runningProjectId ? $runningElapsed : 0.0);

                return new DashboardProjectSummaryData(
                    id: $projectId,
                    name: (string) $row->project_name,
                    color: $row->project_color !== null ? (string) $row->project_color : null,
                    client_name: (string) $row->client_name,
                    hours: $this->decimal($hours),
                    percent_of_month: $monthHours > 0 ? round($hours / $monthHours * 100, 1) : 0.0,
                );
            });
    }

    /**
     * Hours, billable hours and billable amount per day in [$from, $to],
     * keyed by `Y-m-d`.
     *
     * @return DailyTotals
     */
    private function dailyTotals(User $user, CarbonImmutable $from, CarbonImmutable $to, ?TimeEntry $running): array
    {
        $rows = $this->entriesBetween($user, $from, $to)
            ->groupBy('time_entries.spent_on')
            ->selectRaw(<<<'SQL'
                time_entries.spent_on AS spent_on,
                SUM(time_entries.hours) AS hours,
                SUM(CASE WHEN time_entries.is_billable = 1 THEN time_entries.hours ELSE 0 END) AS billable_hours,
                SUM(CASE WHEN time_entries.is_billable = 1 THEN time_entries.hours * COALESCE(time_entries.hourly_rate, 0) ELSE 0 END) AS billable_amount
            SQL)
            ->get();

        $daily = [];
        foreach ($rows as $row) {
            $daily[CarbonImmutable::parse((string) $row->spent_on)->toDateString()] = [
                'hours' => (float) $row->hours,
                'billable_hours' => (float) $row->billable_hours,
                'billable_amount' => (float) $row->billable_amount,
            ];
        }

        if ($running !== null && $running->spent_on->between($from, $to)) {
            $date = $running->spent_on->toDateString();
            $elapsed = $this->elapsedHours($running);
            $totals = $daily[$date] ?? ['hours' => 0.0, 'billable_hours' => 0.0, 'billable_amount' => 0.0];

            $totals['hours'] += $elapsed;
            if ($running->is_billable) {
                $totals['billable_hours'] += $elapsed;
                $totals['billable_amount'] += $elapsed * (float) $running->hourly_rate;
            }

            $daily[$date] = $totals;
        }

        return $daily;
    }

    /**
     * Entries of the user with a `spent_on` in [$from, $to]. The upper bound
     * is exclusive on the next day so it also matches drivers that store the
     * date with a time part.
     */
    private function entriesBetween(User $user, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('time_entries')
            ->where('time_entries.user_id', $user->id)
            ->whereNull('time_entries.deleted_at')
            ->where('time_entries.spent_on', '>=', $from->toDateString())
            ->where('time_entries.spent_on', '<', $to->addDay()->toDateString());
    }

    /**
     * @param  DailyTotals  $daily
     * @param  'hours'|'billable_hours'|'billable_amount'  $field
     */
    private function sum(array $daily, string $field, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $total = 0.0;

        foreach ($daily as $date => $totals) {
            if ($date >= $fromDate && $date <= $toDate) {
                $total += $totals[$field];
            }
        }

        return $total;
    }

    /**
     * @param  DailyTotals  $daily
     */
    private function billableRatio(array $daily, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $hours = $this->sum($daily, 'hours', $from, $to);

        if ($hours <= 0.0) {
            return null;
        }

        return round($this->sum($daily, 'billable_hours', $from, $to) / $hours * 100, 1);
    }

    private function deltaPercent(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /** Time on the clock since the timer started, on top of the stored hours. */
    private function elapsedHours(TimeEntry $entry): float
    {
        return max(0.0, $entry->currentHours() - (float) $entry->hours);
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
