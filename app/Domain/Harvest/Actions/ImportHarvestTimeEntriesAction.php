<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\Task;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ImportHarvestTimeEntriesAction
{
    public function __construct(private readonly HarvestClient $client) {}

    public function handle(HarvestImportResultData $result, ?CarbonInterface $updatedSince = null, ?callable $tick = null): void
    {
        $userIds = User::query()->whereNotNull('harvest_id')->pluck('id', 'harvest_id');
        $projectIds = Project::withTrashed()->whereNotNull('harvest_id')->pluck('id', 'harvest_id');
        $taskIds = Task::query()->whereNotNull('harvest_id')->pluck('id', 'harvest_id');

        foreach ($this->client->timeEntries($updatedSince) as $record) {
            $harvestId = (int) $record['id'];
            $userId = $userIds->get((int) ($record['user']['id'] ?? 0));
            $projectId = $projectIds->get((int) ($record['project']['id'] ?? 0));
            $taskId = $taskIds->get((int) ($record['task']['id'] ?? 0));

            if ($userId === null || $projectId === null) {
                Log::warning("Harvest import: skipping time entry {$harvestId}, user or project is unknown.");

                continue;
            }

            $isRunning = (bool) ($record['is_running'] ?? false);
            $timerStartedAt = is_string($record['timer_started_at'] ?? null) ? Carbon::parse($record['timer_started_at']) : null;

            // Harvest's `hours` already includes the running timer; locally the
            // timer's elapsed time is added on top of `hours`, so store the
            // base value for running entries.
            $hours = $isRunning && isset($record['hours_without_timer']) ? $record['hours_without_timer'] : ($record['hours'] ?? 0);

            $attributes = [
                'user_id' => $userId,
                'project_id' => $projectId,
                'task_id' => $taskId,
                'spent_on' => (string) $record['spent_date'],
                'hours' => $hours,
                'notes' => $record['notes'] ?? null,
                'is_billable' => (bool) ($record['billable'] ?? true),
                'hourly_rate' => $record['billable_rate'] ?? null,
                'is_locked' => (bool) ($record['is_locked'] ?? false),
                'is_running' => $isRunning,
                'timer_started_at' => $isRunning ? $timerStartedAt : null,
            ];

            $entry = TimeEntry::withTrashed()->where('harvest_id', $harvestId)->first();

            if ($entry === null) {
                TimeEntry::create([...$attributes, 'is_billed' => (bool) ($record['is_billed'] ?? false), 'harvest_id' => $harvestId]);
                $result->time_entries->created++;
            } else {
                // An entry that is on a local invoice keeps its billed state.
                if ($entry->invoice_id === null) {
                    $attributes['is_billed'] = (bool) ($record['is_billed'] ?? false);
                }

                $entry->update($attributes);
                $result->time_entries->updated++;
            }

            if ($tick !== null) {
                $tick();
            }
        }
    }
}
