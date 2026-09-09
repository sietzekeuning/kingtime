<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Actions;

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Invoice\Models\InvoiceLine;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\Moneybird\Models\MoneybirdConnection;
use App\Domain\Moneybird\Services\MoneybirdClient;

/**
 * Creates the invoice as a draft sales invoice in Moneybird, creating the
 * contact first when the client is not linked yet. Safe to call twice: an
 * invoice that already carries a Moneybird id is returned untouched.
 */
class PushInvoiceToMoneybirdAction
{
    /**
     * @throws MoneybirdException When the invoice has no owner with a Moneybird connection.
     */
    public function handle(Invoice $invoice): Invoice
    {
        if ($invoice->isPushedToMoneybird()) {
            return $invoice;
        }

        $invoice->loadMissing(['client', 'lines', 'user']);

        $moneybird = $invoice->user === null ? null : MoneybirdClient::forUser($invoice->user);

        if ($moneybird === null) {
            throw MoneybirdException::notConfigured();
        }

        $contactId = $this->ensureContact($moneybird, $invoice->client);

        $payload = $moneybird->createSalesInvoice($this->salesInvoiceAttributes($moneybird, $invoice, $contactId));

        $invoice->fillFromMoneybird($payload)->save();

        if ($invoice->specification !== null && $invoice->moneybird_invoice_id !== null) {
            $moneybird->createSalesInvoiceNote($invoice->moneybird_invoice_id, $invoice->specification);
        }

        return $invoice;
    }

    private function ensureContact(MoneybirdClient $moneybird, Client $client): string
    {
        if ($client->moneybird_contact_id !== null) {
            return $client->moneybird_contact_id;
        }

        $contact = $moneybird->findContactByName($client->name)
            ?? $moneybird->createContact($this->contactAttributes($client));

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
    private function salesInvoiceAttributes(MoneybirdClient $moneybird, Invoice $invoice, string $contactId): array
    {
        $connection = $moneybird->connection();

        $attributes = [
            'contact_id' => $contactId,
            'invoice_date' => now()->toDateString(),
            'reference' => $invoice->periodLabel(),
            'currency' => $invoice->currency,
            'prices_are_incl_tax' => false,
            'details_attributes' => $invoice->lines->map(fn (InvoiceLine $line) => $this->detailAttributes($connection, $line))->values()->all(),
        ];

        if ($connection->workflow_id !== null && $connection->workflow_id !== '') {
            $attributes['workflow_id'] = $connection->workflow_id;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function detailAttributes(MoneybirdConnection $connection, InvoiceLine $line): array
    {
        $detail = [
            'description' => $line->description,
            'amount' => $line->quantity,
            'price' => $line->unit_price,
        ];

        foreach (['tax_rate_id', 'ledger_account_id'] as $key) {
            $value = $connection->{$key};

            if (is_string($value) && $value !== '') {
                $detail[$key] = $value;
            }
        }

        return $detail;
    }
}
