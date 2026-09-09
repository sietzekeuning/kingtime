<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Client\Models\Client;
use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Services\HarvestClient;
use Carbon\CarbonInterface;

class ImportHarvestClientsAction
{
    public function handle(HarvestClient $client, HarvestImportResultData $result, ?CarbonInterface $updatedSince = null, ?callable $tick = null): void
    {
        foreach ($client->clients($updatedSince) as $record) {
            $harvestId = (int) $record['id'];
            $attributes = [
                'name' => (string) $record['name'],
                'is_active' => (bool) ($record['is_active'] ?? true),
                'address' => $record['address'] ?? null,
                'currency' => (string) ($record['currency'] ?? 'EUR'),
            ];

            $client = Client::withTrashed()->where('harvest_id', $harvestId)->first();

            if ($client === null) {
                Client::create([...$attributes, 'harvest_id' => $harvestId]);
                $result->clients->created++;
            } else {
                $client->update($attributes);
                $result->clients->updated++;
            }

            if ($tick !== null) {
                $tick();
            }
        }
    }
}
