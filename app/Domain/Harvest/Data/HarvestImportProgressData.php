<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Data;

use App\Domain\Harvest\Actions\ImportFromHarvestAction;
use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\Harvest\Models\HarvestImport;
use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Facades\Cache;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Live state of the queued import, kept in the cache so the settings page
 * can poll it while the job runs on a worker.
 */
#[TypeScript]
class HarvestImportProgressData extends BaseData
{
    public const CACHE_KEY = 'harvest:import:progress';

    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        public HarvestImportStatus $status,
        public ?string $step,
        public string $message,
        public HarvestImportResultData $counts,
        public ?int $harvest_import_id = null,
        public ?string $started_at = null,
        public ?string $finished_at = null,
        public ?string $error = null,
    ) {}

    public static function queued(): self
    {
        return new self(
            status: HarvestImportStatus::Queued,
            step: null,
            message: 'Waiting for a queue worker to pick up the import.',
            counts: new HarvestImportResultData,
        );
    }

    public static function running(HarvestImport $import, string $step, HarvestImportResultData $counts): self
    {
        $label = str_replace('_', ' ', $step);
        $message = $step === ImportFromHarvestAction::DONE
            ? 'Finishing up.'
            : sprintf('Importing %s (%d so far).', $label, $counts->{$step}->total());

        return new self(
            status: HarvestImportStatus::Running,
            step: $step,
            message: $message,
            counts: $counts,
            harvest_import_id: $import->id,
            started_at: $import->started_at->toIso8601String(),
        );
    }

    public static function finished(HarvestImport $import): self
    {
        $counts = HarvestImportResultData::restore($import->counts) ?? new HarvestImportResultData;

        return new self(
            status: HarvestImportStatus::Finished,
            step: null,
            message: sprintf('Import finished, %d records synced.', $counts->total()),
            counts: $counts,
            harvest_import_id: $import->id,
            started_at: $import->started_at->toIso8601String(),
            finished_at: $import->finished_at?->toIso8601String(),
        );
    }

    public static function failed(string $error, ?HarvestImport $import = null): self
    {
        return new self(
            status: HarvestImportStatus::Failed,
            step: null,
            message: 'Import failed.',
            counts: new HarvestImportResultData,
            harvest_import_id: $import?->id,
            started_at: $import?->started_at->toIso8601String(),
            finished_at: $import?->finished_at?->toIso8601String(),
            error: $error,
        );
    }

    public static function load(): ?self
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? self::from($cached) : null;
    }

    public function store(): self
    {
        Cache::put(self::CACHE_KEY, $this->toArray(), self::CACHE_TTL_SECONDS);

        return $this;
    }

    public static function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
