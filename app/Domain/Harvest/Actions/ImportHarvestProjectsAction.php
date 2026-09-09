<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Client\Models\Client;
use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\Project\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Harvest can bill a project per task or per person; those rates are not
 * modelled here, so a project only keeps its own `hourly_rate` (the rate of
 * every imported entry comes from Harvest's `billable_rate` anyway).
 */
class ImportHarvestProjectsAction
{
    /** Harvest `budget_by` values whose `budget` is expressed in hours. */
    private const HOUR_BUDGETS = ['project', 'task', 'person'];

    public function handle(HarvestClient $client, HarvestImportResultData $result, ?CarbonInterface $updatedSince = null, ?callable $tick = null): void
    {
        $ownerId = $client->connection()->user_id;

        foreach ($client->projects($updatedSince) as $record) {
            $harvestId = (int) $record['id'];
            $clientHarvestId = (int) ($record['client']['id'] ?? 0);
            $clientId = Client::ownedBy($ownerId)->withTrashed()->where('harvest_id', $clientHarvestId)->value('id');

            if ($clientId === null) {
                Log::warning("Harvest import: skipping project {$harvestId}, client {$clientHarvestId} is unknown.");

                continue;
            }

            $budgetBy = (string) ($record['budget_by'] ?? 'none');
            $attributes = [
                'client_id' => $clientId,
                'name' => (string) $record['name'],
                'code' => $record['code'] ?? null,
                'is_billable' => (bool) ($record['is_billable'] ?? true),
                'hourly_rate' => $record['hourly_rate'] ?? null,
                'budget_hours' => in_array($budgetBy, self::HOUR_BUDGETS, true) ? ($record['budget'] ?? null) : null,
                'budget_amount' => $budgetBy === 'project_cost' ? ($record['cost_budget'] ?? $record['budget'] ?? null) : null,
                'is_active' => (bool) ($record['is_active'] ?? true),
                'starts_on' => $record['starts_on'] ?? null,
                'ends_on' => $record['ends_on'] ?? null,
                'notes' => $record['notes'] ?? null,
            ];

            $project = Project::ownedBy($ownerId)->withTrashed()->where('harvest_id', $harvestId)->first();

            if ($project === null) {
                Project::create([...$attributes, 'user_id' => $ownerId, 'harvest_id' => $harvestId]);
                $result->projects->created++;
            } else {
                $project->update($attributes);
                $result->projects->updated++;
            }

            if ($tick !== null) {
                $tick();
            }
        }
    }
}
