<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Jobs;

use App\Domain\Harvest\Actions\RunHarvestImportAction;
use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Models\HarvestImport;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Background import started from the settings page. Progress is published
 * to the cache so the page can poll it.
 */
class ImportFromHarvestJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public readonly ?CarbonInterface $updatedSince = null,
        public readonly bool $full = false,
    ) {}

    public function handle(RunHarvestImportAction $runImport): void
    {
        try {
            $import = $runImport->handle(
                $this->updatedSince,
                $this->full,
                fn (HarvestImport $import, string $step, HarvestImportResultData $result) => HarvestImportProgressData::running($import, $step, $result)->store(),
            );

            HarvestImportProgressData::finished($import)->store();
        } catch (Throwable $exception) {
            HarvestImportProgressData::failed($exception->getMessage())->store();

            throw $exception;
        }
    }
}
