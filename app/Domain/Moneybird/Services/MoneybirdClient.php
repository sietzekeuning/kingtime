<?php

declare(strict_types=1);

namespace App\Domain\Moneybird\Services;

use App\Domain\Moneybird\Exceptions\MoneybirdException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin adapter over the Moneybird API v2. Every method returns the decoded
 * JSON payload and throws a MoneybirdException on a non-2xx answer, so the
 * actions never have to look at HTTP details.
 *
 * @phpstan-type MoneybirdPayload array<string, mixed>
 */
class MoneybirdClient
{
    public function isConfigured(): bool
    {
        return $this->accessToken() !== '' && $this->administrationId() !== '';
    }

    /**
     * Finds a contact by (company) name. Moneybird's query search is fuzzy,
     * so the result is narrowed to an exact, case-insensitive name match.
     *
     * @return MoneybirdPayload|null
     */
    public function findContactByName(string $name): ?array
    {
        /** @var array<int, MoneybirdPayload> $contacts */
        $contacts = $this->request('GET', 'contacts', query: ['query' => $name]);

        foreach ($contacts as $contact) {
            $candidates = [
                (string) ($contact['company_name'] ?? ''),
                trim(sprintf('%s %s', $contact['firstname'] ?? '', $contact['lastname'] ?? '')),
            ];

            foreach ($candidates as $candidate) {
                if ($candidate !== '' && mb_strtolower($candidate) === mb_strtolower($name)) {
                    return $contact;
                }
            }
        }

        return null;
    }

    /**
     * @param  MoneybirdPayload  $attributes
     * @return MoneybirdPayload
     */
    public function createContact(array $attributes): array
    {
        return $this->request('POST', 'contacts', ['contact' => $attributes]);
    }

    /**
     * Creates a draft sales invoice and returns Moneybird's payload, extended
     * with a `url` to the invoice in the Moneybird web app.
     *
     * @param  MoneybirdPayload  $attributes
     * @return MoneybirdPayload
     */
    public function createSalesInvoice(array $attributes): array
    {
        return $this->withUrl($this->request('POST', 'sales_invoices', ['sales_invoice' => $attributes]));
    }

    /**
     * @return MoneybirdPayload
     */
    public function getSalesInvoice(string $id): array
    {
        return $this->withUrl($this->request('GET', "sales_invoices/{$id}"));
    }

    /**
     * Sends the invoice through Moneybird (email by default), which also
     * moves it from draft to open and assigns the invoice number.
     *
     * @param  MoneybirdPayload  $options
     * @return MoneybirdPayload
     */
    public function sendSalesInvoice(string $id, array $options = []): array
    {
        return $this->withUrl($this->request(
            'PATCH',
            "sales_invoices/{$id}/send_invoice",
            ['sales_invoice_sending' => $options + ['delivery_method' => 'Email']],
        ));
    }

    /**
     * Attaches an internal note to the invoice, which is where the hour
     * specification goes so it stays with the invoice in Moneybird.
     *
     * @return MoneybirdPayload
     */
    public function createSalesInvoiceNote(string $id, string $note): array
    {
        return $this->request('POST', "sales_invoices/{$id}/notes", ['note' => ['note' => $note, 'todo' => false]]);
    }

    /**
     * Generic read of any endpoint under the administration, for example
     * `contacts`, `documents/purchase_invoices` or `sales_invoices/1/payments`.
     * Moneybird's own query params apply: `page`, `per_page`, `query` and
     * `filter` (a comma separated list such as `period:this_month,state:open`).
     *
     * @param  array<string, string|int>  $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, query: $query);
    }

    /**
     * Contacts, optionally narrowed with Moneybird's fuzzy `query` search.
     *
     * @return array<int, MoneybirdPayload>
     */
    public function contacts(?string $query = null, int $page = 1, int $perPage = 50): array
    {
        $parameters = ['page' => $page, 'per_page' => $perPage];

        if ($query !== null && trim($query) !== '') {
            $parameters['query'] = trim($query);
        }

        return $this->get('contacts', $parameters);
    }

    /**
     * @return MoneybirdPayload
     */
    public function contact(string $id): array
    {
        /** @var MoneybirdPayload $contact */
        $contact = $this->get("contacts/{$id}");

        return $contact;
    }

    /**
     * Sales invoices matching Moneybird filters such as
     * `['period' => 'this_month', 'state' => 'open', 'contact_id' => '1']`.
     *
     * @param  array<string, string|int|null>  $filters
     * @return array<int, MoneybirdPayload>
     */
    public function salesInvoices(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        /** @var array<int, MoneybirdPayload> $invoices */
        $invoices = $this->get('sales_invoices', $this->listQuery($filters, $page, $perPage));

        return array_map(fn (array $invoice): array => $this->withUrl($invoice), $invoices);
    }

    /**
     * @param  array<string, string|int|null>  $filters
     * @return array<int, MoneybirdPayload>
     */
    public function purchaseInvoices(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        /** @var array<int, MoneybirdPayload> $documents */
        $documents = $this->get('documents/purchase_invoices', $this->listQuery($filters, $page, $perPage));

        return array_map(fn (array $document): array => $this->withDocumentUrl($document), $documents);
    }

    /**
     * @param  array<string, string|int|null>  $filters
     * @return array<int, MoneybirdPayload>
     */
    public function receipts(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        /** @var array<int, MoneybirdPayload> $documents */
        $documents = $this->get('documents/receipts', $this->listQuery($filters, $page, $perPage));

        return array_map(fn (array $document): array => $this->withDocumentUrl($document), $documents);
    }

    /**
     * @return array<int, MoneybirdPayload>
     */
    public function financialAccounts(): array
    {
        return $this->get('financial_accounts');
    }

    /**
     * @return array<int, MoneybirdPayload>
     */
    public function products(): array
    {
        return $this->get('products');
    }

    /**
     * The configured administration (name, currency, language) out of the
     * ones the token can reach, or null when the token cannot see it.
     *
     * @return MoneybirdPayload|null
     */
    public function administration(): ?array
    {
        if (! $this->isConfigured()) {
            throw MoneybirdException::notConfigured();
        }

        $response = $this->http()->get(sprintf('%s/administrations.json', rtrim($this->baseUrl(), '/')));

        if ($response->failed()) {
            throw MoneybirdException::fromResponse('GET', 'administrations', $response);
        }

        /** @var array<int, MoneybirdPayload> $administrations */
        $administrations = $this->decode($response);

        foreach ($administrations as $administration) {
            if ((string) ($administration['id'] ?? '') === $this->administrationId()) {
                return $administration;
            }
        }

        return null;
    }

    /**
     * @return array<int, MoneybirdPayload>
     */
    public function taxRates(): array
    {
        return $this->request('GET', 'tax_rates', query: ['filter' => 'tax_rate_type:sales_invoice']);
    }

    /**
     * @return array<int, MoneybirdPayload>
     */
    public function ledgerAccounts(): array
    {
        return $this->request('GET', 'ledger_accounts');
    }

    /**
     * @return array<int, MoneybirdPayload>
     */
    public function workflows(): array
    {
        return $this->request('GET', 'workflows');
    }

    public function salesInvoiceUrl(string $id): string
    {
        return sprintf('https://moneybird.com/%s/sales_invoices/%s', $this->administrationId(), $id);
    }

    public function documentUrl(string $id): string
    {
        return sprintf('https://moneybird.com/%s/documents/%s', $this->administrationId(), $id);
    }

    /**
     * Moneybird's list query: `page`, `per_page` and the filters joined as
     * `filter=key:value,key:value`. Empty filter values are dropped.
     *
     * @param  array<string, string|int|null>  $filters
     * @return array<string, string|int>
     */
    public static function listQuery(array $filters, int $page = 1, int $perPage = 50): array
    {
        $query = ['page' => max(1, $page), 'per_page' => max(1, $perPage)];
        $parts = [];

        foreach ($filters as $key => $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $parts[] = sprintf('%s:%s', $key, trim((string) $value));
        }

        if ($parts !== []) {
            $query['filter'] = implode(',', $parts);
        }

        return $query;
    }

    /**
     * @param  MoneybirdPayload|null  $body
     * @param  array<string, string|int>  $query
     * @return array<mixed>
     */
    protected function request(string $method, string $path, ?array $body = null, array $query = []): array
    {
        if (! $this->isConfigured()) {
            throw MoneybirdException::notConfigured();
        }

        $url = sprintf('%s/%s/%s.json', rtrim($this->baseUrl(), '/'), $this->administrationId(), $path);

        $response = match ($method) {
            'GET' => $this->http()->get($url, $query),
            'POST' => $this->http()->post($url, $body ?? []),
            'PATCH' => $this->http()->patch($url, $body ?? []),
            'DELETE' => $this->http()->delete($url),
            default => throw new MoneybirdException("Unsupported HTTP method [{$method}]."),
        };

        if ($response->failed()) {
            throw MoneybirdException::fromResponse($method, $path, $response);
        }

        return $this->decode($response);
    }

    /**
     * @return array<mixed>
     */
    protected function decode(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<mixed>  $invoice
     * @return MoneybirdPayload
     */
    protected function withUrl(array $invoice): array
    {
        /** @var MoneybirdPayload $invoice */
        if (isset($invoice['id'])) {
            $invoice['url'] = $this->salesInvoiceUrl((string) $invoice['id']);
        }

        return $invoice;
    }

    /**
     * @param  array<mixed>  $document
     * @return MoneybirdPayload
     */
    protected function withDocumentUrl(array $document): array
    {
        /** @var MoneybirdPayload $document */
        if (isset($document['id'])) {
            $document['url'] = $this->documentUrl((string) $document['id']);
        }

        return $document;
    }

    protected function http(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    protected function baseUrl(): string
    {
        return (string) config('services.moneybird.base_url', 'https://moneybird.com/api/v2');
    }

    protected function administrationId(): string
    {
        return (string) config('services.moneybird.administration_id', '');
    }

    protected function accessToken(): string
    {
        return (string) config('services.moneybird.access_token', '');
    }
}
