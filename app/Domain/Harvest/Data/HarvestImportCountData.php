<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HarvestImportCountData extends BaseData
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated;
    }
}
