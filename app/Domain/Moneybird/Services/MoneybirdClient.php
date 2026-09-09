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

    /**
     * @param  MoneybirdPayload|null  $body
     * @param  array<string, string>  $query
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
