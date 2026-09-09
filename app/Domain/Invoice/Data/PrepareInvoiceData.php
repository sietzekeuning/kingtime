<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The prepare-invoice form: which client, which period, and optionally a
 * subset of the unbilled entries in that period.
 */
#[TypeScript]
class PrepareInvoiceData extends BaseData
{
    /**
     * @param  array<int, int>|null  $time_entry_ids
     */
    public function __construct(
        #[Required, Exists('clients', 'id')]
        public int $client_id,
        #[Required, Date]
        public string $period_starts_on,
        #[Required, Date, AfterOrEqual('period_starts_on')]
        public string $period_ends_on,
        public ?array $time_entry_ids = null,
        public ?string $notes = null,
        public bool $push_to_moneybird = false,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'time_entry_ids' => ['nullable', 'array'],
            'time_entry_ids.*' => ['integer', 'exists:time_entries,id'],
        ];
    }
}
