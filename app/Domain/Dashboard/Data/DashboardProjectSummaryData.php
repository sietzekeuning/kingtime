<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A row in "Top projects this month": hours on the project and its share
 * of everything logged this month.
 */
#[TypeScript]
class DashboardProjectSummaryData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $color,
        public string $client_name,
        public string $hours,
        public float $percent_of_month,
    ) {}
}
