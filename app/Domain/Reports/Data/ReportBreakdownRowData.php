<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A row in the per-client or per-project breakdown of the range.
 * `share_percent` is the row's share of all hours in the range; `client_name`
 * and `color` are only set for project rows.
 */
#[TypeScript]
class ReportBreakdownRowData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $client_name,
        public ?string $color,
        public string $hours,
        public string $billable_hours,
        public string $earned,
        public int $entry_count,
        public float $share_percent,
    ) {}
}
