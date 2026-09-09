<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Totals over the requested range next to the totals of the range of equal
 * length right before it, with the percentage change between the two.
 * A delta is null when the previous range has nothing to compare against.
 */
#[TypeScript]
class ReportTotalsData extends BaseData
{
    public function __construct(
        public string $hours,
        public string $billable_hours,
        public string $earned,
        public string $invoiced,
        public int $entry_count,
        public string $previous_hours,
        public string $previous_billable_hours,
        public string $previous_earned,
        public string $previous_invoiced,
        public ?float $hours_delta_percent,
        public ?float $billable_hours_delta_percent,
        public ?float $earned_delta_percent,
        public ?float $invoiced_delta_percent,
    ) {}
}
