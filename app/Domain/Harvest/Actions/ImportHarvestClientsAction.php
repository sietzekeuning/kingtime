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
        $ownerId = $client->connection()->user_id;

        foreach ($client->clients($updatedSince) as $record) {
            $harvestId = (int) $record['id'];
            $attributes = [
                'name' => (string) $record['name'],
                'is_active' => (bool) ($record['is_active'] ?? true),
                'address' => $record['address'] ?? null,
                'currency' => (string) ($record['currency'] ?? 'EUR'),
            ];

            $existing = Client::ownedBy($ownerId)->withTrashed()->where('harvest_id', $harvestId)->first();

            if ($existing === null) {
                Client::create([...$attributes, 'user_id' => $ownerId, 'harvest_id' => $harvestId]);
                $result->clients->created++;
            } else {
                $existing->update($attributes);
                $result->clients->updated++;
            }

            if ($tick !== null) {
                $tick();
            }
        }
    }
}
