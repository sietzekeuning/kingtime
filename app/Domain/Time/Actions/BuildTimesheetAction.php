<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Data\TimesheetCellData;
use App\Domain\Time\Data\TimesheetData;
use App\Domain\Time\Data\TimesheetDayData;
use App\Domain\Time\Data\TimesheetRowData;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds the week (Monday to Sunday) around a date for one user: a total
 * per day, the week total, one grid row per project with a cell per day,
 * and the entries of the selected day. Running timers count their elapsed
 * time so the totals are live.
 */
class BuildTimesheetAction
{
    public function handle(User $user, CarbonInterface $date): TimesheetData
    {
        $selected = $date->toImmutable()->startOfDay();
        $weekStart = $selected->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addDays(6);

        $entries = $this->entriesBetween($user, $weekStart, $weekEnd);

        $byDay = $entries->groupBy(fn (TimeEntry $entry) => $entry->spent_on->toDateString());

        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $byDay): TimesheetDayData {
            $day = $weekStart->addDays($offset);
            /** @var Collection<int, TimeEntry> $dayEntries */
            $dayEntries = $byDay->get($day->toDateString(), collect());

            return $this->day($day, $dayEntries);
        });

        /** @var Collection<int, TimeEntry> $selectedEntries */
        $selectedEntries = $byDay->get($selected->toDateString(), collect());

        return new TimesheetData(
            selected_date: $selected->toDateString(),
            week_start: $weekStart->toDateString(),
            week_end: $weekEnd->toDateString(),
            week_total: $this->formatHours($this->sumHours($entries)),
            day_total: $this->formatHours($this->sumHours($selectedEntries)),
            days: $days,
            rows: $this->rows($entries, $weekStart),
            entries: $selectedEntries->values()->map(fn (TimeEntry $entry) => TimeEntryData::fromModel($entry)),
            previous_week_project_ids: $this->entriesBetween($user, $weekStart->subDays(7), $weekStart->subDay())
                ->pluck('project_id')->unique()->sort()->values()->all(),
        );
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     */
    public function day(CarbonImmutable $day, Collection $entries): TimesheetDayData
    {
        $today = now()->toImmutable()->startOfDay();

        return new TimesheetDayData(
            date: $day->toDateString(),
            weekday: $day->format('D'),
            day_of_month: $day->day,
            total_hours: $this->formatHours($this->sumHours($entries)),
            entries_count: $entries->count(),
            is_today: $day->isSameDay($today),
            is_weekend: $day->isWeekend(),
            is_future: $day->greaterThan($today),
        );
    }

    /**
     * @return Collection<int, TimeEntry>
     */
    public function entriesBetween(User $user, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $user->timeEntries()
            ->with('project.client')
            ->whereDate('spent_on', '>=', $from->toDateString())
            ->whereDate('spent_on', '<=', $to->toDateString())
            ->orderBy('spent_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * One row per project that has hours in the week, sorted by name.
     *
     * @param  Collection<int, TimeEntry>  $entries
     * @return Collection<int, TimesheetRowData>
     */
    private function rows(Collection $entries, CarbonImmutable $weekStart): Collection
    {
        return $entries
            ->groupBy('project_id')
            ->map(function (Collection $projectEntries) use ($weekStart): TimesheetRowData {
                /** @var TimeEntry $first */
                $first = $projectEntries->first();
                $byDay = $projectEntries->groupBy(fn (TimeEntry $entry) => $entry->spent_on->toDateString());

                $cells = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $byDay): TimesheetCellData {
                    $date = $weekStart->addDays($offset)->toDateString();
                    /** @var Collection<int, TimeEntry> $dayEntries */
                    $dayEntries = $byDay->get($date, collect());

                    return $this->cell($date, $dayEntries);
                });

                return new TimesheetRowData(
                    project_id: $first->project_id,
                    project_name: $first->project->name,
                    project_code: $first->project->code,
                    project_color: $first->project->color,
                    client_name: $first->project->client->name,
                    total_hours: $this->formatHours($this->sumHours($projectEntries)),
                    is_locked: $projectEntries->every(fn (TimeEntry $entry) => $entry->isLocked()),
                    cells: $cells,
                );
            })
            ->sortBy(fn (TimesheetRowData $row) => mb_strtolower($row->project_name))
            ->values();
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     */
    private function cell(string $date, Collection $entries): TimesheetCellData
    {
        /** @var TimeEntry|null $single */
        $single = $entries->count() === 1 ? $entries->first() : null;
        $notes = $entries->pluck('notes')->filter()->implode("\n");

        return new TimesheetCellData(
            date: $date,
            hours: $this->formatHours($this->sumHours($entries)),
            entries_count: $entries->count(),
            entry_id: $single?->id,
            is_locked: $entries->isNotEmpty() && $entries->contains(fn (TimeEntry $entry) => $entry->isLocked()),
            is_running: $entries->contains(fn (TimeEntry $entry) => $entry->is_running),
            notes: $notes !== '' ? $notes : null,
        );
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     */
    private function sumHours(Collection $entries): float
    {
        return $entries->sum(fn (TimeEntry $entry) => $entry->currentHours());
    }

    public function formatHours(float $hours): string
    {
        return number_format(round($hours, 2), 2, '.', '');
    }
}
