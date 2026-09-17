<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Controllers;

use App\Domain\Desktop\Actions\BuildDesktopStateAction;
use App\Domain\Desktop\Data\DesktopStateData;
use App\Domain\Time\Actions\StartTimerAction;
use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The play button on an entry in the day view: the timer continues on top
 * of the hours the entry already has, so half an hour typed in by hand
 * becomes 0:30 and counting. Any other running timer stops first.
 */
class DesktopEntryTimerController
{
    public function __invoke(Request $request, TimeEntry $timeEntry, StartTimerAction $startTimer, BuildDesktopStateAction $buildState): DesktopStateData
    {
        abort_unless($timeEntry->user_id === $request->user()?->id, 403);

        /** @var User $user */
        $user = $request->user();

        try {
            $startTimer->handle($timeEntry);
        } catch (TimeEntryLockedException $exception) {
            throw ValidationException::withMessages(['time_entry' => $exception->getMessage()]);
        }

        return $buildState->handle($user);
    }
}
