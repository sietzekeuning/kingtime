<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Controllers;

use App\Domain\Desktop\Actions\BuildDesktopStateAction;
use App\Domain\Desktop\Data\DesktopStateData;
use App\Domain\Project\Models\Project;
use App\Domain\Shared\Validation\Owned;
use App\Domain\Time\Actions\StartProjectTimerAction;
use App\Domain\Time\Actions\StopTimerAction;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Http\Request;

/**
 * The play and stop buttons of the menu bar app. Both answer with the full
 * desktop state, so the app never needs a second round trip to redraw.
 */
class DesktopTimerController
{
    public function store(Request $request, StartProjectTimerAction $startTimer, BuildDesktopStateAction $buildState): DesktopStateData
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', Owned::exists('projects')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $startTimer->handle($user, Project::query()->findOrFail((int) $validated['project_id']), $validated['notes'] ?? null);

        return $buildState->handle($user);
    }

    public function destroy(Request $request, StopTimerAction $stopTimer, BuildDesktopStateAction $buildState): DesktopStateData
    {
        /** @var User $user */
        $user = $request->user();

        $user->timeEntries()->where('is_running', true)->get()
            ->each(fn (TimeEntry $entry) => $stopTimer->handle($entry));

        return $buildState->handle($user);
    }
}
