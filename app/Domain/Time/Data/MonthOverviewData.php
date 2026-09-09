<?php

declare(strict_types=1);

namespace App\Domain\Time\Data;

use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A calendar month with the hours per day, so a glance shows which
 * working days still have nothing logged.
 */
#[TypeScript]
class MonthOverviewData extends BaseData
{
    /**
     * @param  Collection<int, TimesheetDayData>  $days
     */
    public function __construct(
        public string $month_start,
        public string $month_end,
        public string $total_hours,
        /** Weekdays up to today. */
        public int $working_days,
        /** Weekdays up to today with at least one entry. */
        public int $booked_days,
        /** Weekdays up to today without an entry. */
        public int $missing_days,
        /** @var Collection<int, TimesheetDayData> */
        #[DataCollectionOf(TimesheetDayData::class)]
        public Collection $days,
    ) {}
}
