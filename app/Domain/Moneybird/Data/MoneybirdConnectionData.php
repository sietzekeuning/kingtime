<?php

declare(strict_types=1);

namespace App\Domain\Moneybird\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a user types to connect Moneybird: a personal API token from
 * https://moneybird.com/user/applications, the administration id and the
 * optional ids applied to every invoice line. An empty token keeps the one
 * that is already stored, so the settings can be changed without retyping it.
 */
#[TypeScript]
class MoneybirdConnectionData extends BaseData
{
    public function __construct(
        #[Max(255)]
        public ?string $access_token,
        #[Required, Regex('/^\d+$/'), Max(32)]
        public string $administration_id,
        #[Regex('/^\d+$/'), Max(32)]
        public ?string $tax_rate_id = null,
        #[Regex('/^\d+$/'), Max(32)]
        public ?string $ledger_account_id = null,
        #[Regex('/^\d+$/'), Max(32)]
        public ?string $workflow_id = null,
    ) {}
}
