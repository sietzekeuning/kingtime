<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\Harvest\Exceptions\HarvestException;
use App\Domain\Harvest\Jobs\ImportFromHarvestJob;
use App\Domain\User\Models\User;

/**
 * Queue an import from the settings page, unless one is already in flight.
 */
class StartHarvestImportAction
{
    public function handle(User $user): HarvestImportProgressData
    {
        $current = HarvestImportProgressData::load($user->id);

        if ($current !== null && $current->status->isActive()) {
            return $current;
        }

        $connection = $user->harvestConnection()->first();

        if ($connection === null) {
            return HarvestImportProgressData::failed(HarvestException::notConfigured()->getMessage())->store($user->id);
        }

        $progress = HarvestImportProgressData::queued()->store($user->id);

        ImportFromHarvestJob::dispatch($connection);

        return $progress;
    }
}
