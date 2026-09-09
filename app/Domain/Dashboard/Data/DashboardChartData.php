<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Hours per week for the "Weekly hours" chart. One label and one value per
 * week, oldest first, ending with the current (partial) week. `range` is
 * `3m`, `6m` or `1y`.
 */
#[TypeScript]
class DashboardChartData extends BaseData
{
    /**
     * @param  array<int, string>  $labels
     * @param  array<int, float>  $values
     */
    public function __construct(
        public string $range,
        public array $labels,
        public array $values,
    ) {}
}
