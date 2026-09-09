<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;

/**
 * Removes a project row from the week grid: every open entry of that
 * project in the week. Billed, locked and running entries stay.
 */
class DeleteTimesheetRowAction
{
    public function handle(User $user, int $projectId, CarbonImmutable $weekStart): int
    {
        $weekStart = $weekStart->startOfWeek(CarbonImmutable::MONDAY);

        return $user->timeEntries()
            ->where('project_id', $projectId)
            ->whereDate('spent_on', '>=', $weekStart->toDateString())
            ->whereDate('spent_on', '<=', $weekStart->addDays(6)->toDateString())
            ->get()
            ->reject(fn (TimeEntry $entry) => $entry->isLocked() || $entry->is_running)
            ->each(fn (TimeEntry $entry) => $entry->delete())
            ->count();
    }
}
