<?php

declare(strict_types=1);

namespace App\Domain\Time\Controllers;

use App\Domain\Shared\Validation\Owned;
use App\Domain\Time\Actions\SaveTimesheetCellAction;
use App\Domain\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimesheetCellController
{
    public function __invoke(Request $request, SaveTimesheetCellAction $saveCell): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', Owned::exists('projects')],
            'spent_on' => ['required', 'date_format:Y-m-d'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $saveCell->handle(
            $user,
            (int) $validated['project_id'],
            $validated['spent_on'],
            isset($validated['hours']) ? (string) $validated['hours'] : null,
        );

        return back();
    }
}
