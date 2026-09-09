<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Data\TimesheetData;
use App\Domain\Time\Data\TimesheetDayData;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds the week view (Monday to Sunday) around a date for one user: a
 * total per day, the week total, and the entries of the selected day.
 * Running timers count their elapsed time so the totals are live.
 */
class BuildTimesheetAction
{
    public function handle(User $user, CarbonInterface $date): TimesheetData
    {
        $selected = $date->toImmutable()->startOfDay();
        $weekStart = $selected->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addDays(6);
        $today = now()->toDateString();

        $entries = $user->timeEntries()
            ->with(['project.client', 'task'])
            ->whereDate('spent_on', '>=', $weekStart->toDateString())
            ->whereDate('spent_on', '<=', $weekEnd->toDateString())
            ->orderBy('spent_on')
            ->orderBy('id')
            ->get();

        $byDay = $entries->groupBy(fn (TimeEntry $entry) => $entry->spent_on->toDateString());

        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $byDay, $today): TimesheetDayData {
            $day = $weekStart->addDays($offset);
            /** @var Collection<int, TimeEntry> $dayEntries */
            $dayEntries = $byDay->get($day->toDateString(), collect());

            return new TimesheetDayData(
                date: $day->toDateString(),
                weekday: $day->format('D'),
                day_of_month: $day->day,
                total_hours: $this->formatHours($this->sumHours($dayEntries)),
                entries_count: $dayEntries->count(),
                is_today: $day->toDateString() === $today,
            );
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
            entries: $selectedEntries->values()->map(fn (TimeEntry $entry) => TimeEntryData::fromModel($entry)),
        );
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     */
    private function sumHours(Collection $entries): float
    {
        return $entries->sum(fn (TimeEntry $entry) => $entry->currentHours());
    }

    private function formatHours(float $hours): string
    {
        return number_format(round($hours, 2), 2, '.', '');
    }
}
