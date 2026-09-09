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
        public HarvestImportCountData $users = new HarvestImportCountData,
        public HarvestImportCountData $clients = new HarvestImportCountData,
        public HarvestImportCountData $projects = new HarvestImportCountData,
        public HarvestImportCountData $tasks = new HarvestImportCountData,
        public HarvestImportCountData $task_assignments = new HarvestImportCountData,
        public HarvestImportCountData $time_entries = new HarvestImportCountData,
    ) {}

    public function total(): int
    {
        return $this->users->total()
            + $this->clients->total()
            + $this->projects->total()
            + $this->tasks->total()
            + $this->task_assignments->total()
            + $this->time_entries->total();
    }

    /**
     * @param  array<string, array{created?: int, updated?: int}>|null  $counts
     */
    public static function restore(?array $counts): ?self
    {
        return $counts === null ? null : self::from($counts);
    }
}
