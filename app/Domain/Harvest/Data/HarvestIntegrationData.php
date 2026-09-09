<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Data;

use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the integrations settings page needs to show for Harvest.
 */
#[TypeScript]
class HarvestIntegrationData extends BaseData
{
    /**
     * @param  Collection<int, HarvestImportData>  $imports
     */
    public function __construct(
        public bool $configured,
        public ?string $account_name,
        public ?string $account_email,
        public ?string $account_error,
        public ?HarvestImportData $last_import,
        /** @var Collection<int, HarvestImportData> */
        #[DataCollectionOf(HarvestImportData::class)]
        public Collection $imports,
        public ?HarvestImportProgressData $progress,
    ) {}
}
