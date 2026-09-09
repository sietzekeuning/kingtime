<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Writes one cell of the week grid: the hours of a project on a day. An
 * empty cell gets a new entry, a cell with one entry updates it, and
 * clearing the cell deletes that entry. Cells with several entries or a
 * running timer are edited in the day view instead.
 */
class SaveTimesheetCellAction
{
    public function __construct(private LogTimeAction $logTime) {}

    public function handle(User $user, int $projectId, string $date, ?string $hours): ?TimeEntry
    {
        $entries = $user->timeEntries()
            ->where('project_id', $projectId)
            ->whereDate('spent_on', $date)
            ->get();

        if ($entries->count() > 1) {
            throw ValidationException::withMessages([
                'hours' => 'This day has several entries for the project. Edit them in the day view.',
            ]);
        }

        /** @var TimeEntry|null $entry */
        $entry = $entries->first();

        if ($entry?->isLocked()) {
            throw TimeEntryLockedException::for($entry);
        }

        if ($entry?->is_running) {
            throw ValidationException::withMessages([
                'hours' => 'Stop the timer before changing these hours.',
            ]);
        }

        $amount = $hours === null || $hours === '' ? 0.0 : (float) $hours;

        if ($amount <= 0) {
            $entry?->delete();

            return null;
        }

        if ($entry !== null) {
            $entry->update(['hours' => number_format($amount, 2, '.', '')]);

            return $entry;
        }

        return $this->logTime->handle(new TimeEntryData(
            id: null,
            project_id: $projectId,
            spent_on: $date,
            hours: number_format($amount, 2, '.', ''),
        ), $user);
    }
}
