<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Controllers;

use App\Domain\Time\Actions\BuildTimesheetAction;
use App\Domain\Time\Data\TimesheetData;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The week grid of the menu bar app: the same Monday to Sunday timesheet
 * the web app draws, for the week around `date` (today when it is left
 * out). Walking through the weeks is a call per week.
 */
class DesktopWeekController
{
    public function __invoke(Request $request, BuildTimesheetAction $buildTimesheet): TimesheetData
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return $buildTimesheet->handle($user, isset($validated['date']) ? CarbonImmutable::parse($validated['date']) : CarbonImmutable::now());
    }
}
