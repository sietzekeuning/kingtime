<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Data;

use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DashboardStatsData extends BaseData
{
    /**
     * @param  Collection<int, DashboardStatData>  $items
     */
    public function __construct(
        #[DataCollectionOf(DashboardStatData::class)]
        public Collection $items,
    ) {}
}
