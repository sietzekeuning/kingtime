<?php

declare(strict_types=1);

namespace App\Domain\Time\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One column of the week strip on the timesheet. */
#[TypeScript]
class TimesheetDayData extends BaseData
{
    public function __construct(
        public string $date,
        public string $weekday,
        public int $day_of_month,
        public string $total_hours,
        public int $entries_count,
        public bool $is_today,
    ) {}
}
