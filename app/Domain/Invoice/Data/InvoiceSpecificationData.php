<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Data;

use App\Domain\Client\Data\ClientData;
use App\Domain\Shared\Data\BaseData;
use App\Domain\Time\Data\TimeEntryData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The preview of an invoice before it exists: the client's unbilled hours in
 * a period, grouped into invoice lines, plus the plain-text specification
 * that goes to Moneybird with the invoice.
 */
#[TypeScript]
class InvoiceSpecificationData extends BaseData
{
    /**
     * @param  Collection<int, InvoiceLineData>  $lines
     * @param  Collection<int, TimeEntryData>  $entries
     */
    public function __construct(
        public ClientData $client,
        public string $period_starts_on,
        public string $period_ends_on,
        /** @var Collection<int, InvoiceLineData> */
        #[DataCollectionOf(InvoiceLineData::class)]
        public Collection $lines,
        public string $subtotal,
        public string $total_hours,
        /** @var Collection<int, TimeEntryData> */
        #[DataCollectionOf(TimeEntryData::class)]
        public Collection $entries,
        public int $unpriced_entries,
        public string $specification_text,
    ) {}

    public function isEmpty(): bool
    {
        return $this->entries->isEmpty();
    }
}
