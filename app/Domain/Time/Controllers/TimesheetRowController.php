<?php

declare(strict_types=1);

namespace App\Domain\Time\Controllers;

use App\Domain\Shared\Validation\Owned;
use App\Domain\Time\Actions\DeleteTimesheetRowAction;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimesheetRowController
{
    public function __invoke(Request $request, DeleteTimesheetRowAction $deleteRow): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', Owned::exists('projects')],
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $deleted = $deleteRow->handle($user, (int) $validated['project_id'], CarbonImmutable::parse($validated['week_start']));

        return back()->with('toast', [
            'type' => 'success',
            'message' => $deleted === 1 ? 'Time entry deleted.' : "{$deleted} time entries deleted.",
        ]);
    }
}
