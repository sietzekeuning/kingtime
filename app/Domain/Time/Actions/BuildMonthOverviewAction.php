<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Time\Data\MonthOverviewData;
use App\Domain\Time\Data\TimesheetDayData;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The calendar month around a date for one user, with the hours per day
 * and a count of the working days that still have nothing logged.
 */
class BuildMonthOverviewAction
{
    public function __construct(private BuildTimesheetAction $timesheet) {}

    public function handle(User $user, CarbonInterface $date): MonthOverviewData
    {
        $monthStart = $date->toImmutable()->startOfMonth();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();

        $entries = $this->timesheet->entriesBetween($user, $monthStart, $monthEnd);
        $byDay = $entries->groupBy(fn (TimeEntry $entry) => $entry->spent_on->toDateString());

        $days = collect(range(0, $monthStart->daysInMonth - 1))->map(function (int $offset) use ($monthStart, $byDay): TimesheetDayData {
            $day = $monthStart->addDays($offset);
            /** @var Collection<int, TimeEntry> $dayEntries */
            $dayEntries = $byDay->get($day->toDateString(), collect());

            return $this->timesheet->day($day, $dayEntries);
        });

        $workingDays = $days->filter(fn (TimesheetDayData $day) => ! $day->is_weekend && ! $day->is_future);
        $bookedDays = $workingDays->filter(fn (TimesheetDayData $day) => $day->entries_count > 0);

        return new MonthOverviewData(
            month_start: $monthStart->toDateString(),
            month_end: $monthEnd->toDateString(),
            total_hours: $this->timesheet->formatHours($entries->sum(fn (TimeEntry $entry) => $entry->currentHours())),
            working_days: $workingDays->count(),
            booked_days: $bookedDays->count(),
            missing_days: $workingDays->count() - $bookedDays->count(),
            days: $days,
        );
    }
}
