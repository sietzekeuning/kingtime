<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\Time\Models\TimeEntry;
use Illuminate\Support\Facades\DB;

/**
 * Starts (or resumes) the timer on an entry. A user has at most one running
 * timer, so any other running entry of theirs is stopped first and keeps the
 * time it accumulated.
 */
class StartTimerAction
{
    public function __construct(private StopTimerAction $stopTimer) {}

    public function handle(TimeEntry $entry): TimeEntry
    {
        if ($entry->isLocked()) {
            throw TimeEntryLockedException::for($entry);
        }

        if ($entry->is_running) {
            return $entry;
        }

        return DB::transaction(function () use ($entry): TimeEntry {
            TimeEntry::query()
                ->where('user_id', $entry->user_id)
                ->where('is_running', true)
                ->whereKeyNot($entry->id)
                ->get()
                ->each(fn (TimeEntry $running) => $this->stopTimer->handle($running));

            $entry->update([
                'is_running' => true,
                'timer_started_at' => now(),
            ]);

            return $entry;
        });
    }
}
