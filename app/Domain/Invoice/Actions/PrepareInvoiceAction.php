<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Actions;

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Data\InvoiceLineData;
use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Invoice\Exceptions\NothingToInvoiceException;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Models\TimeEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates a draft invoice from the client's unbilled hours in a period and
 * locks those hours to it. The invoice only exists locally until
 * PushInvoiceToMoneybirdAction sends it on.
 */
class PrepareInvoiceAction
{
    public function __construct(private BuildInvoiceSpecificationAction $buildSpecification) {}

    /**
     * @param  array<int, int|string>|null  $timeEntryIds  Restrict to these entries; null takes every unbilled entry in the period.
     * @param  string|null  $notes  Free text kept on the local invoice (not sent to Moneybird).
     *
     * @throws NothingToInvoiceException
     */
    public function handle(Client $client, CarbonInterface $from, CarbonInterface $to, ?array $timeEntryIds = null, ?string $notes = null): Invoice
    {
        return DB::transaction(function () use ($client, $from, $to, $timeEntryIds, $notes): Invoice {
            $specification = $this->buildSpecification->handle($client, $from, $to, $timeEntryIds);

            if ($specification->isEmpty()) {
                throw NothingToInvoiceException::forPeriod($client->name, $from->format('d-m-Y'), $to->format('d-m-Y'));
            }

            $invoice = Invoice::query()->create([
                'client_id' => $client->id,
                'status' => InvoiceStatus::Draft,
                'period_starts_on' => $from->toDateString(),
                'period_ends_on' => $to->toDateString(),
                'subtotal' => $specification->subtotal,
                'total' => $specification->subtotal,
                'currency' => $client->currency,
                'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'specification' => $specification->specification_text,
            ]);

            $invoice->lines()->createMany(
                $specification->lines->map(fn (InvoiceLineData $line) => $line->toUpdateArray())->all(),
            );

            $entryIds = $specification->entries->map(fn (TimeEntryData $entry) => $entry->id)->all();

            $linked = TimeEntry::query()
                ->whereIn('id', $entryIds)
                ->unbilled()
                ->update(['invoice_id' => $invoice->id, 'is_billed' => true, 'is_locked' => true]);

            if ($linked !== count($entryIds)) {
                throw new NothingToInvoiceException('Some of the selected hours were invoiced in the meantime. Refresh the preview and try again.');
            }

            return $invoice->load(['client', 'lines']);
        });
    }
}
