<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Project\Models\Project;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;

/**
 * Starts a timer on a project for today, the way the menu bar app does it:
 * pick a project, press play. Stopping and starting again on the same
 * project and notes during the day resumes today's entry instead of
 * scattering the day over many small ones, which is what Harvest does and
 * what the invoice specification reads best with.
 */
class StartProjectTimerAction
{
    public function __construct(
        private LogTimeAction $logTime,
        private StartTimerAction $startTimer,
    ) {}

    public function handle(User $user, Project $project, ?string $notes): TimeEntry
    {
        $notes = trim((string) $notes) !== '' ? trim((string) $notes) : null;

        $entry = $user->timeEntries()
            ->where('project_id', $project->id)
            ->whereDate('spent_on', now()->toDateString())
            ->where('is_billed', false)
            ->where('is_locked', false)
            ->where('notes', $notes)
            ->latest('id')
            ->first();

        if ($entry === null) {
            $entry = $this->logTime->handle(TimeEntryData::validateAndCreate([
                'project_id' => $project->id,
                'spent_on' => now()->toDateString(),
                'hours' => '0.00',
                'notes' => $notes,
            ]), $user);
        }

        return $this->startTimer->handle($entry)->refresh();
    }
}
