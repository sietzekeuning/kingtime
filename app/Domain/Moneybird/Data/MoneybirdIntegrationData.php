<?php

declare(strict_types=1);

namespace App\Domain\Moneybird\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the integrations settings page shows for Moneybird. The token
 * itself is never part of it.
 */
#[TypeScript]
class MoneybirdIntegrationData extends BaseData
{
    public function __construct(
        public bool $configured,
        public ?string $administration_id,
        public ?string $administration_name,
        public ?string $administration_error,
        public ?string $tax_rate_id,
        public ?string $ledger_account_id,
        public ?string $workflow_id,
    ) {}
}
