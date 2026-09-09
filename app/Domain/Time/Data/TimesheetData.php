<?php

declare(strict_types=1);

namespace App\Domain\Time\Data;

use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The week around a selected day: per-day totals for the strip, and the
 * selected day's entries in full.
 */
#[TypeScript]
class TimesheetData extends BaseData
{
    /**
     * @param  Collection<int, TimesheetDayData>  $days
     * @param  Collection<int, TimeEntryData>  $entries
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
        /** @var Collection<int, TimeEntryData> */
        #[DataCollectionOf(TimeEntryData::class)]
        public Collection $entries,
    ) {}
}
