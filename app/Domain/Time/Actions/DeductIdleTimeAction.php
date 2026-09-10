<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\Time\Models\TimeEntry;

/**
 * Takes a stretch of idle time off an entry, for when the desktop app
 * noticed the computer sat untouched while the timer ran. On a running
 * entry the timer's start is moved forward, so the live clock drops by the
 * idle time at once; whatever the running stretch cannot absorb comes off
 * the stored hours. Optionally the timer is stopped in the same go.
 */
class DeductIdleTimeAction
{
    public function __construct(private StopTimerAction $stopTimer) {}

    public function handle(TimeEntry $entry, int $seconds, bool $stop = false): TimeEntry
    {
        if ($entry->isLocked()) {
            throw TimeEntryLockedException::for($entry);
        }

        $seconds = max(0, $seconds);
        $attributes = [];

        if ($entry->is_running && $entry->timer_started_at !== null) {
            $fromTimer = min($seconds, $entry->elapsedSeconds());
            $attributes['timer_started_at'] = $entry->timer_started_at->addSeconds($fromTimer);
            $seconds -= $fromTimer;
        }

        if ($seconds > 0) {
            $attributes['hours'] = number_format(max(0, (float) $entry->hours - $seconds / 3600), 2, '.', '');
        }

        if ($attributes !== []) {
            $entry->update($attributes);
        }

        return $stop ? $this->stopTimer->handle($entry) : $entry;
    }
}
