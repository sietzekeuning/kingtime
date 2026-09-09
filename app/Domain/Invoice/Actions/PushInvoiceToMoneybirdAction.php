<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Actions;

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Invoice\Models\InvoiceLine;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\Moneybird\Services\MoneybirdClient;

/**
 * Creates the invoice as a draft sales invoice in Moneybird, creating the
 * contact first when the client is not linked yet. Safe to call twice: an
 * invoice that already carries a Moneybird id is returned untouched.
 */
class PushInvoiceToMoneybirdAction
{
    public function __construct(private MoneybirdClient $moneybird) {}

    /**
     * @throws MoneybirdException
     */
    public function handle(Invoice $invoice): Invoice
    {
        if ($invoice->isPushedToMoneybird()) {
            return $invoice;
        }

        if (! $this->moneybird->isConfigured()) {
            throw MoneybirdException::notConfigured();
        }

        $invoice->loadMissing(['client', 'lines']);

        $contactId = $this->ensureContact($invoice->client);

        $payload = $this->moneybird->createSalesInvoice($this->salesInvoiceAttributes($invoice, $contactId));

        $invoice->fillFromMoneybird($payload)->save();

        if ($invoice->specification !== null && $invoice->moneybird_invoice_id !== null) {
            $this->moneybird->createSalesInvoiceNote($invoice->moneybird_invoice_id, $invoice->specification);
        }

        return $invoice;
    }

    private function ensureContact(Client $client): string
    {
        if ($client->moneybird_contact_id !== null) {
            return $client->moneybird_contact_id;
        }

        $contact = $this->moneybird->findContactByName($client->name)
            ?? $this->moneybird->createContact($this->contactAttributes($client));

        $client->moneybird_contact_id = (string) $contact['id'];
        $client->save();

        return $client->moneybird_contact_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function contactAttributes(Client $client): array
    {
        $attributes = ['company_name' => $client->name];

        if ($client->email !== null) {
            $attributes['send_invoices_to_email'] = $client->email;
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $client->address) ?: []), fn (string $line) => $line !== ''));

        foreach ($lines as $index => $line) {
            if (preg_match('/^(\d{4}\s?[A-Z]{2})\s+(.+)$/i', $line, $matches) === 1) {
                $attributes['zipcode'] = strtoupper(str_replace(' ', '', $matches[1]));
                $attributes['city'] = $matches[2];

                continue;
            }

            $key = $index === 0 ? 'address1' : 'address2';
            $attributes[$key] = isset($attributes[$key]) ? $attributes[$key].', '.$line : $line;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function salesInvoiceAttributes(Invoice $invoice, string $contactId): array
    {
        $attributes = [
            'contact_id' => $contactId,
            'invoice_date' => now()->toDateString(),
            'reference' => $invoice->periodLabel(),
            'currency' => $invoice->currency,
            'prices_are_incl_tax' => false,
            'details_attributes' => $invoice->lines->map(fn (InvoiceLine $line) => $this->detailAttributes($line))->values()->all(),
        ];

        $workflowId = config('services.moneybird.workflow_id');

        if (is_string($workflowId) && $workflowId !== '') {
            $attributes['workflow_id'] = $workflowId;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function detailAttributes(InvoiceLine $line): array
    {
        $detail = [
            'description' => $line->description,
            'amount' => $line->quantity,
            'price' => $line->unit_price,
        ];

        foreach (['tax_rate_id', 'ledger_account_id'] as $key) {
            $value = config("services.moneybird.{$key}");

            if (is_string($value) && $value !== '') {
                $detail[$key] = $value;
            }
        }

        return $detail;
    }
}
