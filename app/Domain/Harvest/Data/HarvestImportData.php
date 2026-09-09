<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Data;

use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\Harvest\Models\HarvestImport;
use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HarvestImportData extends BaseData
{
    public function __construct(
        public int $id,
        public HarvestImportStatus $status,
        public string $started_at,
        public ?string $finished_at,
        public ?string $updated_since,
        public ?HarvestImportResultData $counts,
        public ?string $error,
        public ?int $duration_seconds,
    ) {}

    public static function fromModel(HarvestImport $import): self
    {
        return new self(
            id: $import->id,
            status: $import->status,
            started_at: $import->started_at->toIso8601String(),
            finished_at: $import->finished_at?->toIso8601String(),
            updated_since: $import->updated_since?->toIso8601String(),
            counts: HarvestImportResultData::restore($import->counts),
            error: $import->error,
            duration_seconds: $import->finished_at !== null ? (int) $import->started_at->diffInSeconds($import->finished_at) : null,
        );
    }
}
