<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Only the hours of the Harvest user behind the token come over: they land
 * on the account that connected Harvest. Hours of other people in the
 * Harvest account belong to nobody here and are left out.
 */
class ImportHarvestTimeEntriesAction
{
    public function handle(HarvestClient $client, HarvestImportResultData $result, ?CarbonInterface $updatedSince = null, ?callable $tick = null): void
    {
        $connection = $client->connection();
        $ownerId = $connection->user_id;
        $harvestUserId = $connection->harvest_user_id;

        if ($harvestUserId === null) {
            Log::warning('Harvest import: skipping time entries, the Harvest user behind the token is unknown.');

            return;
        }

        $projectIds = Project::ownedBy($ownerId)->withTrashed()->whereNotNull('harvest_id')->pluck('id', 'harvest_id');

        foreach ($client->timeEntries($updatedSince, harvestUserId: $harvestUserId) as $record) {
            $harvestId = (int) $record['id'];
            $projectId = $projectIds->get((int) ($record['project']['id'] ?? 0));

            if ((int) ($record['user']['id'] ?? 0) !== $harvestUserId) {
                continue;
            }

            if ($projectId === null) {
                Log::warning("Harvest import: skipping time entry {$harvestId}, its project is unknown.");

                continue;
            }

            $isRunning = (bool) ($record['is_running'] ?? false);
            $timerStartedAt = is_string($record['timer_started_at'] ?? null) ? Carbon::parse($record['timer_started_at']) : null;

            // Harvest's `hours` already includes the running timer; locally the
            // timer's elapsed time is added on top of `hours`, so store the
            // base value for running entries.
            $hours = $isRunning && isset($record['hours_without_timer']) ? $record['hours_without_timer'] : ($record['hours'] ?? 0);

            $attributes = [
                'user_id' => $ownerId,
                'project_id' => $projectId,
                'spent_on' => (string) $record['spent_date'],
                'hours' => $hours,
                'notes' => self::notes($record),
                'is_billable' => (bool) ($record['billable'] ?? true),
                'hourly_rate' => $record['billable_rate'] ?? null,
                'is_locked' => (bool) ($record['is_locked'] ?? false),
                'is_running' => $isRunning,
                'timer_started_at' => $isRunning ? $timerStartedAt : null,
            ];

            $entry = TimeEntry::ownedBy($ownerId)->withTrashed()->where('harvest_id', $harvestId)->first();

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

    /**
     * Tasks are not modelled locally. An entry that has notes keeps them as
     * they are; one without notes keeps the Harvest task name as its notes so
     * that information is not lost.
     *
     * @param  array<string, mixed>  $record
     */
    private static function notes(array $record): ?string
    {
        $notes = $record['notes'] ?? null;

        if (is_string($notes) && trim($notes) !== '') {
            return $notes;
        }

        $taskName = $record['task']['name'] ?? null;

        return is_string($taskName) && trim($taskName) !== '' ? trim($taskName) : null;
    }
}
