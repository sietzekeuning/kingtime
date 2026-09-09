<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a user types to connect Harvest: the account id and a personal
 * access token from https://id.getharvest.com/developers.
 */
#[TypeScript]
class HarvestConnectionData extends BaseData
{
    public function __construct(
        #[Required, Regex('/^\d+$/'), Max(32)]
        public string $account_id,
        #[Required, Max(255)]
        public string $access_token,
    ) {}
}
