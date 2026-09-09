<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Jobs;

use App\Domain\Harvest\Actions\RunHarvestImportAction;
use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Models\HarvestConnection;
use App\Domain\Harvest\Models\HarvestImport;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Background import started from the settings page. Progress is published
 * to the cache so the page can poll it.
 */
class ImportFromHarvestJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public readonly HarvestConnection $harvestConnection,
        public readonly ?CarbonInterface $updatedSince = null,
        public readonly bool $full = false,
    ) {}

    public function handle(RunHarvestImportAction $runImport): void
    {
        $userId = $this->harvestConnection->user_id;

        try {
            $import = $runImport->handle(
                $this->harvestConnection,
                $this->updatedSince,
                $this->full,
                fn (HarvestImport $import, string $step, HarvestImportResultData $result) => HarvestImportProgressData::running($import, $step, $result)->store($userId),
            );

            HarvestImportProgressData::finished($import)->store($userId);
        } catch (Throwable $exception) {
            HarvestImportProgressData::failed($exception->getMessage())->store($userId);

            throw $exception;
        }
    }
}
