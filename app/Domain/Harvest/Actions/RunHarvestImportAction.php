<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\Harvest\Exceptions\HarvestException;
use App\Domain\Harvest\Models\HarvestConnection;
use App\Domain\Harvest\Models\HarvestImport;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Run one import and log it as a HarvestImport row. Shared by the artisan
 * command (synchronous) and the queued job.
 *
 * The optional progress callback receives `(HarvestImport $import, string $step, HarvestImportResultData $result)`.
 */
class RunHarvestImportAction
{
    public const LOCK_KEY = 'harvest:import:lock';

    public static function lockKey(HarvestConnection $connection): string
    {
        return self::LOCK_KEY.':'.$connection->user_id;
    }

    private const LOCK_SECONDS = 3600;

    /** Overlap with the previous run to absorb clock drift between servers. */
    private const INCREMENTAL_OVERLAP_MINUTES = 5;

    public function __construct(private readonly ImportFromHarvestAction $importFromHarvest) {}

    /**
     * @param  CarbonInterface|null  $updatedSince  Explicit lower bound; defaults to the last successful run.
     * @param  bool  $full  Ignore previous runs and fetch everything.
     */
    public function handle(HarvestConnection $connection, ?CarbonInterface $updatedSince = null, bool $full = false, ?callable $progress = null): HarvestImport
    {
        $lock = Cache::lock(self::lockKey($connection), self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw HarvestException::alreadyRunning();
        }

        try {
            $since = $full ? null : ($updatedSince ?? HarvestImport::lastSuccessfulFor($connection->user)?->started_at?->subMinutes(self::INCREMENTAL_OVERLAP_MINUTES));

            $import = HarvestImport::create([
                'user_id' => $connection->user_id,
                'status' => HarvestImportStatus::Running,
                'started_at' => now(),
                'updated_since' => $since,
            ]);

            try {
                $result = $this->importFromHarvest->handle(
                    $connection,
                    $since,
                    $progress === null ? null : fn (string $step, HarvestImportResultData $result) => $progress($import, $step, $result),
                );

                $import->update([
                    'status' => HarvestImportStatus::Finished,
                    'finished_at' => now(),
                    'counts' => $result->toArray(),
                ]);
            } catch (Throwable $exception) {
                $import->update([
                    'status' => HarvestImportStatus::Failed,
                    'finished_at' => now(),
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            return $import->refresh();
        } finally {
            $lock->release();
        }
    }
}
