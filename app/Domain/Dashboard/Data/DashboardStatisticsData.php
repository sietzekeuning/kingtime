<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Data;

use App\Domain\Shared\Data\BaseData;
use App\Domain\Time\Data\TimeEntryData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The "Statistics" panel: how much of this month is billable, the average
 * per working day so far, and the timer that is running right now.
 */
#[TypeScript]
class DashboardStatisticsData extends BaseData
{
    public function __construct(
        /** Percentage of this month's hours that is billable; null without hours. */
        public ?float $billable_ratio,
        public ?float $billable_ratio_previous,
        /** Difference in percentage points vs. last month. */
        public ?float $billable_ratio_delta,
        public string $average_hours_per_working_day,
        public int $working_days_this_month,
        public ?TimeEntryData $running_timer,
    ) {}
}
