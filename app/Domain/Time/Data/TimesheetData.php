<?php

declare(strict_types=1);

namespace App\Domain\Time\Data;

use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The week around a selected day: per-day totals for the strip, the
 * project rows of the week grid, and the selected day's entries in full.
 */
#[TypeScript]
class TimesheetData extends BaseData
{
    /**
     * @param  Collection<int, TimesheetDayData>  $days
     * @param  Collection<int, TimesheetRowData>  $rows
     * @param  Collection<int, TimeEntryData>  $entries
     * @param  array<int, int>  $previous_week_project_ids
     */
    public function __construct(
        public string $selected_date,
        public string $week_start,
        public string $week_end,
        public string $week_total,
        public string $day_total,
        /** @var Collection<int, TimesheetDayData> */
        #[DataCollectionOf(TimesheetDayData::class)]
        public Collection $days,
        /** @var Collection<int, TimesheetRowData> */
        #[DataCollectionOf(TimesheetRowData::class)]
        public Collection $rows,
        /** @var Collection<int, TimeEntryData> */
        #[DataCollectionOf(TimeEntryData::class)]
        public Collection $entries,
        /** Projects that had hours the week before, for "copy from last week". */
        public array $previous_week_project_ids,
    ) {}
}
