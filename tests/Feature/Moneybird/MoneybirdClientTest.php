<?php

declare(strict_types=1);

use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\Moneybird\Services\MoneybirdClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const MONEYBIRD_CLIENT_API = 'moneybird.com/api/v2/123456789';

function moneybirdClientFixture(string $name, int $status = 200): PromiseInterface
{
    return Http::response(file_get_contents(base_path("tests/Fixtures/moneybird/{$name}.json")), $status);
}

beforeEach(function (): void {
    config()->set('services.moneybird', [
        'access_token' => 'secret-token',
        'administration_id' => '123456789',
        'base_url' => 'https://moneybird.com/api/v2',
        'tax_rate_id' => null,
        'ledger_account_id' => null,
        'workflow_id' => null,
    ]);
    $this->client = app(MoneybirdClient::class);
});

it('reads any path under the administration with query parameters', function (): void {
    Http::fake([MONEYBIRD_CLIENT_API.'/documents/general_journal_documents.json*' => Http::response([['id' => '1']])]);

    expect($this->client->get('documents/general_journal_documents', ['page' => 2, 'filter' => 'period:this_month']))->toBe([['id' => '1']]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://moneybird.com/api/v2/123456789/documents/general_journal_documents.json?page=2&filter=period%3Athis_month'
        && $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('builds Moneybird list queries from filters and drops empty ones', function (): void {
    expect(MoneybirdClient::listQuery(['period' => 'this_year', 'state' => null, 'contact_id' => ' '], 3, 25))
        ->toBe(['page' => 3, 'per_page' => 25, 'filter' => 'period:this_year'])
        ->and(MoneybirdClient::listQuery([]))
        ->toBe(['page' => 1, 'per_page' => 50]);
});

it('lists sales invoices, purchase invoices and receipts with urls', function (): void {
    Http::fake([
        MONEYBIRD_CLIENT_API.'/sales_invoices.json*' => moneybirdClientFixture('sales_invoices_list'),
        MONEYBIRD_CLIENT_API.'/documents/purchase_invoices.json*' => moneybirdClientFixture('purchase_invoices_list'),
        MONEYBIRD_CLIENT_API.'/documents/receipts.json*' => moneybirdClientFixture('receipts_list'),
    ]);

    $invoices = $this->client->salesInvoices(['state' => 'open', 'period' => 'this_month'], 2, 10);
    expect($invoices)->toHaveCount(2)
        ->and($invoices[0]['url'])->toBe('https://moneybird.com/123456789/sales_invoices/422000000000000001')
        ->and($this->client->purchaseInvoices()[0]['url'])->toBe('https://moneybird.com/123456789/documents/426000000000000001')
        ->and($this->client->receipts()[0]['url'])->toBe('https://moneybird.com/123456789/documents/427000000000000001');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/sales_invoices.json?page=2&per_page=10&filter=state%3Aopen%2Cperiod%3Athis_month'));
});

it('finds the configured administration', function (): void {
    Http::fake(['moneybird.com/api/v2/administrations.json' => moneybirdClientFixture('administrations')]);

    expect($this->client->administration())->toMatchArray(['id' => '123456789', 'name' => 'King Websites', 'currency' => 'EUR']);

    config()->set('services.moneybird.administration_id', '42');
    expect($this->client->administration())->toBeNull();
});

it('searches contacts with the query parameter only when given', function (): void {
    Http::fake([MONEYBIRD_CLIENT_API.'/contacts.json*' => moneybirdClientFixture('contacts_list')]);

    $this->client->contacts();
    $this->client->contacts('acme', 2);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/contacts.json?page=1&per_page=50'));
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/contacts.json?page=2&per_page=50&query=acme'));
});

it('throws a MoneybirdException with the API error on failure', function (): void {
    Http::fake([MONEYBIRD_CLIENT_API.'/products.json*' => moneybirdClientFixture('error_unprocessable', 422)]);

    $this->client->products();
})->throws(MoneybirdException::class, 'Moneybird GET products failed with HTTP 422');

it('refuses to read when not configured', function (): void {
    config()->set('services.moneybird.administration_id', null);
    Http::fake();

    expect(fn () => $this->client->get('contacts'))->toThrow(MoneybirdException::class, 'not configured')
        ->and(fn () => $this->client->administration())->toThrow(MoneybirdException::class, 'not configured');

    Http::assertNothingSent();
});
