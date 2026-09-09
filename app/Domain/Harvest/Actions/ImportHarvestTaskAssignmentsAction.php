<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\ProjectTask;
use App\Domain\Project\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class ImportHarvestTaskAssignmentsAction
{
    public function __construct(private readonly HarvestClient $client) {}

    public function handle(HarvestImportResultData $result, ?CarbonInterface $updatedSince = null, ?callable $tick = null): void
    {
        $projectIds = Project::withTrashed()->whereNotNull('harvest_id')->pluck('id', 'harvest_id');
        $taskIds = Task::query()->whereNotNull('harvest_id')->pluck('id', 'harvest_id');

        foreach ($this->client->taskAssignments($updatedSince) as $record) {
            $harvestId = (int) $record['id'];
            $projectId = $projectIds->get((int) ($record['project']['id'] ?? 0));
            $taskId = $taskIds->get((int) ($record['task']['id'] ?? 0));

            if ($projectId === null || $taskId === null) {
                Log::warning("Harvest import: skipping task assignment {$harvestId}, project or task is unknown.");

                continue;
            }

            $attributes = [
                'is_billable' => (bool) ($record['billable'] ?? true),
                'hourly_rate' => $record['hourly_rate'] ?? null,
                'is_active' => (bool) ($record['is_active'] ?? true),
            ];

            // A locally created assignment for the same project/task pair is
            // adopted instead of tripping the unique index.
            $assignment = ProjectTask::query()->where('harvest_id', $harvestId)->first()
                ?? ProjectTask::query()->where('project_id', $projectId)->where('task_id', $taskId)->first();

            if ($assignment === null) {
                ProjectTask::create([...$attributes, 'project_id' => $projectId, 'task_id' => $taskId, 'harvest_id' => $harvestId]);
                $result->task_assignments->created++;
            } else {
                $assignment->update([...$attributes, 'harvest_id' => $harvestId]);
                $result->task_assignments->updated++;
            }

            if ($tick !== null) {
                $tick();
            }
        }
    }
}
