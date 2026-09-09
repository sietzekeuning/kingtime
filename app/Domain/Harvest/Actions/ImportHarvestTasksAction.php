<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\Project\Models\Task;
use Carbon\CarbonInterface;

class ImportHarvestTasksAction
{
    public function __construct(private readonly HarvestClient $client) {}

    public function handle(HarvestImportResultData $result, ?CarbonInterface $updatedSince = null, ?callable $tick = null): void
    {
        foreach ($this->client->tasks($updatedSince) as $record) {
            $harvestId = (int) $record['id'];
            $attributes = [
                'name' => (string) $record['name'],
                'is_billable_by_default' => (bool) ($record['billable_by_default'] ?? true),
                'default_hourly_rate' => $record['default_hourly_rate'] ?? null,
                'is_active' => (bool) ($record['is_active'] ?? true),
            ];

            $task = Task::query()->where('harvest_id', $harvestId)->first();

            if ($task === null) {
                Task::create([...$attributes, 'harvest_id' => $harvestId]);
                $result->tasks->created++;
            } else {
                $task->update($attributes);
                $result->tasks->updated++;
            }

            if ($tick !== null) {
                $tick();
            }
        }
    }
}
