<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One headline tile on the dashboard: the current period's total next to
 * the previous period's, so the card can draw the two progress bars and
 * the percentage delta. `format` is `hours` or `euro`.
 */
#[TypeScript]
class DashboardStatData extends BaseData
{
    public function __construct(
        public string $label,
        public string $value,
        public string $previous_value,
        public ?float $delta_percent,
        public string $current_label,
        public string $previous_label,
        public string $format,
    ) {}
}
