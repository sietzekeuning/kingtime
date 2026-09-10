<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Controllers;

use App\Domain\Desktop\Actions\BuildDesktopStateAction;
use App\Domain\Desktop\Data\DesktopStateData;
use App\Domain\Time\Actions\DeductIdleTimeAction;
use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "You were away for 23 minutes. Deduct it?" The app sends the idle
 * seconds it measured; the entry is the one whose timer was running.
 */
class DesktopIdleTimeController
{
    public function __invoke(Request $request, DeductIdleTimeAction $deductIdleTime, BuildDesktopStateAction $buildState): DesktopStateData
    {
        $validated = $request->validate([
            'time_entry_id' => ['required', 'integer'],
            'seconds' => ['required', 'integer', 'min:1', 'max:86400'],
            'stop' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var TimeEntry|null $entry */
        $entry = $user->timeEntries()->find($validated['time_entry_id']);

        if ($entry === null) {
            throw ValidationException::withMessages(['time_entry_id' => 'That time entry no longer exists.']);
        }

        try {
            $deductIdleTime->handle($entry, (int) $validated['seconds'], (bool) ($validated['stop'] ?? false));
        } catch (TimeEntryLockedException $exception) {
            throw ValidationException::withMessages(['time_entry_id' => $exception->getMessage()]);
        }

        return $buildState->handle($user);
    }
}
