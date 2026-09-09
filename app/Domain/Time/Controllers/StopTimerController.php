<?php

declare(strict_types=1);

namespace App\Domain\Time\Controllers;

use App\Domain\Time\Actions\StopTimerAction;
use App\Domain\Time\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StopTimerController
{
    public function __invoke(Request $request, TimeEntry $timeEntry, StopTimerAction $stopTimer): RedirectResponse
    {
        abort_unless($timeEntry->user_id === $request->user()?->id, 403);

        $stopTimer->handle($timeEntry);

        return back()->with('toast', ['type' => 'success', 'message' => 'Timer stopped.']);
    }
}
