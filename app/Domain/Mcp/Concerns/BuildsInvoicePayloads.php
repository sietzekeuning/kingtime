<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Concerns;

use App\Domain\Invoice\Data\InvoiceLineData;
use App\Domain\Invoice\Data\InvoiceSpecificationData;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Invoice\Models\InvoiceLine;
use App\Domain\Time\Data\TimeEntryData;
use Carbon\CarbonImmutable;
use Laravel\Mcp\Request;

/**
 * Shared by the invoice tools: the client/period arguments and the compact
 * shapes of a specification preview and of a prepared invoice.
 */
trait BuildsInvoicePayloads
{
    /**
     * @return array<string, array<int, string>>
     */
    protected static function invoicePeriodRules(): array
    {
        return [
            'client_id' => ['nullable', 'integer'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * The requested period, defaulting to the previous calendar month like
     * the web form does.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function invoicePeriod(Request $request): array
    {
        $lastMonth = CarbonImmutable::today()->subMonthNoOverflow();

        return [
            $this->dateArgument($request, 'from', $lastMonth->startOfMonth()),
            $this->dateArgument($request, 'to', $lastMonth->endOfMonth()->startOfDay()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function specificationPayload(InvoiceSpecificationData $specification): array
    {
        return [
            'client' => [
                'id' => $specification->client->id,
                'name' => $specification->client->name,
                'currency' => $specification->client->currency,
            ],
            'period_starts_on' => $specification->period_starts_on,
            'period_ends_on' => $specification->period_ends_on,
            'subtotal' => $specification->subtotal,
            'total_hours' => $specification->total_hours,
            'entry_count' => $specification->entries->count(),
            'unpriced_entries' => $specification->unpriced_entries,
            'lines' => $specification->lines->map(fn (InvoiceLineData $line) => [
                'description' => $line->description,
                'project_id' => $line->project_id,
                'task_id' => $line->task_id,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'amount' => $line->amount,
            ])->values()->all(),
            'entries' => $specification->entries->map(fn (TimeEntryData $entry) => $this->entryDataPayload($entry))->values()->all(),
            'specification_text' => $specification->specification_text,
        ];
    }

    /**
     * Expects `client`, `lines` and `timeEntries` to be loaded.
     *
     * @return array<string, mixed>
     */
    protected function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'status' => $invoice->status->value,
            'number' => $invoice->number,
            'client_id' => $invoice->client_id,
            'client' => $invoice->client->name,
            'period_starts_on' => $invoice->period_starts_on?->toDateString(),
            'period_ends_on' => $invoice->period_ends_on?->toDateString(),
            'subtotal' => $invoice->subtotal,
            'total' => $invoice->total,
            'currency' => $invoice->currency,
            'total_hours' => self::decimal($invoice->lines->sum(fn (InvoiceLine $line) => (float) $line->quantity)),
            'entry_count' => $invoice->timeEntries->count(),
            'notes' => $invoice->notes,
            'lines' => $invoice->lines->map(fn (InvoiceLine $line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'amount' => $line->amount,
            ])->values()->all(),
            'moneybird_invoice_id' => $invoice->moneybird_invoice_id,
            'moneybird_url' => $invoice->moneybird_url,
            'specification_text' => $invoice->specification,
        ];
    }
}
