<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\Time\Models\TimeEntry;

class DeleteTimeEntryAction
{
    public function handle(TimeEntry $entry): void
    {
        if ($entry->isLocked()) {
            throw TimeEntryLockedException::for($entry);
        }

        $entry->delete();
    }
}
