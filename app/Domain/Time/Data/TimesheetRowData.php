<?php

declare(strict_types=1);

namespace App\Domain\Time\Data;

use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One project row of the week grid: seven cells and a total. */
#[TypeScript]
class TimesheetRowData extends BaseData
{
    /**
     * @param  Collection<int, TimesheetCellData>  $cells
     */
    public function __construct(
        public int $project_id,
        public string $project_name,
        public ?string $project_code,
        public ?string $project_color,
        public string $client_name,
        public string $total_hours,
        /** Every entry in the row is billed or locked, so nothing can change. */
        public bool $is_locked,
        /** @var Collection<int, TimesheetCellData> */
        #[DataCollectionOf(TimesheetCellData::class)]
        public Collection $cells,
    ) {}
}
