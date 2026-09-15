<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Controllers;

use App\Domain\Shared\Validation\Owned;
use App\Domain\Time\Actions\BuildTimesheetAction;
use App\Domain\Time\Actions\SaveTimesheetCellAction;
use App\Domain\Time\Data\TimesheetData;
use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * One cell of the week grid: the hours of a project on a day. Answers with
 * the whole week again, so the app redraws its totals without a second
 * round trip. Hours that can no longer be changed come back as a 422, the
 * shape the app already knows how to show.
 */
class DesktopWeekCellController
{
    public function __invoke(Request $request, SaveTimesheetCellAction $saveCell, BuildTimesheetAction $buildTimesheet): TimesheetData
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', Owned::exists('projects')],
            'spent_on' => ['required', 'date_format:Y-m-d'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $saveCell->handle(
                $user,
                (int) $validated['project_id'],
                $validated['spent_on'],
                isset($validated['hours']) ? (string) $validated['hours'] : null,
            );
        } catch (TimeEntryLockedException $exception) {
            throw ValidationException::withMessages(['hours' => $exception->getMessage()]);
        }

        return $buildTimesheet->handle($user, CarbonImmutable::parse($validated['spent_on']));
    }
}
