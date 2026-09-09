<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Data;

use App\Domain\Client\Data\ClientData;
use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Shared\Data\Attributes\Derived;
use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class InvoiceData extends BaseData
{
    /**
     * @param  Collection<int, InvoiceLineData>|null  $lines
     */
    public function __construct(
        public ?int $id,
        public int $client_id,
        public ?string $number,
        public InvoiceStatus $status,
        public ?string $period_starts_on,
        public ?string $period_ends_on,
        public ?string $issued_on,
        public ?string $due_on,
        public string $subtotal,
        public string $total,
        public string $currency,
        public ?string $notes = null,
        public ?string $moneybird_invoice_id = null,
        public ?string $moneybird_url = null,
        #[Derived]
        public ?string $created_at = null,
        #[Derived]
        public ?ClientData $client = null,
        #[Derived]
        public ?string $client_name = null,
        /** @var Collection<int, InvoiceLineData>|null */
        #[DataCollectionOf(InvoiceLineData::class)]
        #[Derived]
        public ?Collection $lines = null,
        #[Derived]
        public ?string $total_hours = null,
        #[Derived]
        public ?int $time_entries_count = null,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        $client = $invoice->relationLoaded('client') ? $invoice->client : null;
        $lines = $invoice->relationLoaded('lines') ? $invoice->lines : null;

        return new self(
            id: $invoice->id,
            client_id: $invoice->client_id,
            number: $invoice->number,
            status: $invoice->status,
            period_starts_on: $invoice->period_starts_on?->toDateString(),
            period_ends_on: $invoice->period_ends_on?->toDateString(),
            issued_on: $invoice->issued_on?->toDateString(),
            due_on: $invoice->due_on?->toDateString(),
            subtotal: $invoice->subtotal,
            total: $invoice->total,
            currency: $invoice->currency,
            notes: $invoice->notes,
            moneybird_invoice_id: $invoice->moneybird_invoice_id,
            moneybird_url: $invoice->moneybird_url,
            created_at: $invoice->created_at?->toIso8601String(),
            client: $client !== null ? ClientData::fromModel($client) : null,
            client_name: $client?->name,
            lines: $lines?->map(fn ($line) => InvoiceLineData::fromModel($line)),
            total_hours: $lines !== null ? number_format((float) $lines->sum('quantity'), 2, '.', '') : null,
            time_entries_count: $invoice->getAttribute('time_entries_count'),
        );
    }
}
