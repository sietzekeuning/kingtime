<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Time\Models\TimeEntry;

/**
 * Folds the elapsed time of a running timer into the entry's hours (two
 * decimals) and puts the entry back to rest. A no-op for idle entries.
 */
class StopTimerAction
{
    public function handle(TimeEntry $entry): TimeEntry
    {
        if (! $entry->is_running) {
            return $entry;
        }

        $entry->update([
            'hours' => number_format($entry->currentHours(), 2, '.', ''),
            'is_running' => false,
            'timer_started_at' => null,
        ]);

        return $entry;
    }
}
