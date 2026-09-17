<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Actions;

use App\Domain\Desktop\Data\DesktopLastEntryData;
use App\Domain\Desktop\Data\DesktopStateData;
use App\Domain\Desktop\Data\DesktopTimerData;
use App\Domain\Desktop\Data\DesktopUserData;
use App\Domain\Time\Actions\ListProjectOptionsAction;
use App\Domain\User\Models\User;

class BuildDesktopStateAction
{
    public function __construct(private ListProjectOptionsAction $listProjectOptions) {}

    public function handle(User $user): DesktopStateData
    {
        $running = $user->timeEntries()
            ->where('is_running', true)
            ->with('project.client')
            ->latest('timer_started_at')
            ->first();

        $last = $running !== null ? null : $user->timeEntries()
            ->whereDate('spent_on', now()->toDateString())
            ->where('is_billed', false)
            ->where('is_locked', false)
            ->with('project.client')
            ->latest('updated_at')
            ->latest('id')
            ->first();

        return new DesktopStateData(
            user: DesktopUserData::fromModel($user),
            projects: $this->listProjectOptions->handle($running?->project_id),
            timer: $running !== null ? DesktopTimerData::fromModel($running) : null,
            last_entry: $last !== null ? DesktopLastEntryData::fromModel($last) : null,
            server_time: now()->toIso8601String(),
        );
    }
}
