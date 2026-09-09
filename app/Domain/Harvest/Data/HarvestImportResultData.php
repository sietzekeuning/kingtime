<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Created/updated counters per record type, filled in while an import runs
 * and stored as the `counts` json on the HarvestImport row afterwards.
 */
#[TypeScript]
class HarvestImportResultData extends BaseData
{
    public function __construct(
        public HarvestImportCountData $clients = new HarvestImportCountData,
        public HarvestImportCountData $projects = new HarvestImportCountData,
        public HarvestImportCountData $time_entries = new HarvestImportCountData,
    ) {}

    public function total(): int
    {
        return $this->clients->total()
            + $this->projects->total()
            + $this->time_entries->total();
    }

    /**
     * Imports that ran before tasks were dropped stored `tasks` and
     * `task_assignments` counters, older ones a `users` counter; those keys
     * are ignored.
     *
     * @param  array<string, array{created?: int, updated?: int}>|null  $counts
     */
    public static function restore(?array $counts): ?self
    {
        return $counts === null ? null : self::from($counts);
    }
}
