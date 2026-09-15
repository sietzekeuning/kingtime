<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Controllers;

use App\Domain\Shared\Validation\Owned;
use App\Domain\Time\Actions\BuildTimesheetAction;
use App\Domain\Time\Actions\DeleteTimesheetRowAction;
use App\Domain\Time\Data\TimesheetData;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Removes a project row from the week in the menu bar app: every open entry
 * of that project that week. Billed, locked and running entries stay, and
 * the week comes back as it now stands.
 */
class DesktopWeekRowController
{
    public function __invoke(Request $request, DeleteTimesheetRowAction $deleteRow, BuildTimesheetAction $buildTimesheet): TimesheetData
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', Owned::exists('projects')],
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $weekStart = CarbonImmutable::parse($validated['week_start']);

        $deleteRow->handle($user, (int) $validated['project_id'], $weekStart);

        return $buildTimesheet->handle($user, $weekStart);
    }
}
