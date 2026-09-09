<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\Harvest\Exceptions\HarvestException;
use App\Domain\Harvest\Jobs\ImportFromHarvestJob;
use App\Domain\Harvest\Services\HarvestClient;

/**
 * Queue an import from the settings page, unless one is already in flight.
 */
class StartHarvestImportAction
{
    public function __construct(private readonly HarvestClient $client) {}

    public function handle(): HarvestImportProgressData
    {
        $current = HarvestImportProgressData::load();

        if ($current !== null && $current->status->isActive()) {
            return $current;
        }

        if (! $this->client->isConfigured()) {
            return HarvestImportProgressData::failed(HarvestException::notConfigured()->getMessage())->store();
        }

        $progress = HarvestImportProgressData::queued()->store();

        ImportFromHarvestJob::dispatch();

        return $progress;
    }
}
