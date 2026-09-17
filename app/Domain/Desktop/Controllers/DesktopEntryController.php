<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Controllers;

use App\Domain\Time\Actions\BuildTimesheetAction;
use App\Domain\Time\Actions\DeleteTimeEntryAction;
use App\Domain\Time\Actions\LogTimeAction;
use App\Domain\Time\Actions\UpdateTimeEntryAction;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Data\TimesheetData;
use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The entries of the day view in the menu bar app: add one (half an hour
 * on a project, say), change its hours, notes or project, or delete it.
 * Every call answers with the week around the entry's day, the same payload
 * `GET /api/desktop/week` gives, so the day list and the totals redraw at once.
 */
class DesktopEntryController
{
    public function store(Request $request, LogTimeAction $logTime, BuildTimesheetAction $buildTimesheet): TimesheetData
    {
        /** @var User $user */
        $user = $request->user();

        $entry = $logTime->handle(TimeEntryData::validateAndCreate($request->only(['project_id', 'spent_on', 'hours', 'notes'])), $user);

        return $buildTimesheet->handle($user, $entry->spent_on);
    }

    public function update(Request $request, TimeEntry $timeEntry, UpdateTimeEntryAction $updateTimeEntry, BuildTimesheetAction $buildTimesheet): TimesheetData
    {
        abort_unless($timeEntry->user_id === $request->user()?->id, 403);

        /** @var User $user */
        $user = $request->user();

        if ($timeEntry->is_running) {
            throw ValidationException::withMessages(['hours' => 'Pause the timer before you change this entry.']);
        }

        $data = TimeEntryData::validateAndCreate([
            'project_id' => $timeEntry->project_id,
            'spent_on' => $timeEntry->spent_on->toDateString(),
            'hours' => $timeEntry->hours,
            'notes' => $timeEntry->notes,
            ...$request->only(['project_id', 'hours', 'notes']),
        ]);

        try {
            $updateTimeEntry->handle($timeEntry, $data);
        } catch (TimeEntryLockedException $exception) {
            throw ValidationException::withMessages(['hours' => $exception->getMessage()]);
        }

        return $buildTimesheet->handle($user, $timeEntry->spent_on);
    }

    public function destroy(Request $request, TimeEntry $timeEntry, DeleteTimeEntryAction $deleteTimeEntry, BuildTimesheetAction $buildTimesheet): TimesheetData
    {
        abort_unless($timeEntry->user_id === $request->user()?->id, 403);

        /** @var User $user */
        $user = $request->user();

        try {
            $deleteTimeEntry->handle($timeEntry);
        } catch (TimeEntryLockedException $exception) {
            throw ValidationException::withMessages(['hours' => $exception->getMessage()]);
        }

        return $buildTimesheet->handle($user, $timeEntry->spent_on);
    }
}
