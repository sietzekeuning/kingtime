<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Client\Models\Client;
use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\Project\Enums\BillBy;
use App\Domain\Project\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class ImportHarvestProjectsAction
{
    /** Harvest `budget_by` values whose `budget` is expressed in hours. */
    private const HOUR_BUDGETS = ['project', 'task', 'person'];

    public function __construct(private readonly HarvestClient $client) {}

    public function handle(HarvestImportResultData $result, ?CarbonInterface $updatedSince = null, ?callable $tick = null): void
    {
        foreach ($this->client->projects($updatedSince) as $record) {
            $harvestId = (int) $record['id'];
            $clientHarvestId = (int) ($record['client']['id'] ?? 0);
            $clientId = Client::withTrashed()->where('harvest_id', $clientHarvestId)->value('id');

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
                'bill_by' => self::mapBillBy($record['bill_by'] ?? null),
                'hourly_rate' => $record['hourly_rate'] ?? null,
                'budget_hours' => in_array($budgetBy, self::HOUR_BUDGETS, true) ? ($record['budget'] ?? null) : null,
                'is_active' => (bool) ($record['is_active'] ?? true),
                'starts_on' => $record['starts_on'] ?? null,
                'ends_on' => $record['ends_on'] ?? null,
                'notes' => $record['notes'] ?? null,
            ];

            $project = Project::withTrashed()->where('harvest_id', $harvestId)->first();

            if ($project === null) {
                Project::create([...$attributes, 'harvest_id' => $harvestId]);
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

    /**
     * Harvest uses "Project", "Tasks", "People" and "none". Per-person rates
     * are not modelled here, so they fall back to the project rate.
     */
    public static function mapBillBy(mixed $billBy): BillBy
    {
        return match (mb_strtolower((string) $billBy)) {
            'task', 'tasks' => BillBy::Task,
            'none', '' => BillBy::None,
            default => BillBy::Project,
        };
    }
}
