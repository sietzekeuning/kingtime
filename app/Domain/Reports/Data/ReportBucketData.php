<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One week, month or year of the report. `period` is the sortable key
 * (`2026-09-07`, `2026-09`, `2026`), `label` what the chart and table show
 * (`Week 37 · 2026`, `Sep 2026`, `2026`). `starts_on`/`ends_on` are clipped
 * to the requested range. Hours and money are decimal strings.
 */
#[TypeScript]
class ReportBucketData extends BaseData
{
    public function __construct(
        public string $period,
        public string $label,
        public string $starts_on,
        public string $ends_on,
        public string $hours,
        public string $billable_hours,
        public string $earned,
        public string $invoiced,
        public int $entry_count,
    ) {}
}
