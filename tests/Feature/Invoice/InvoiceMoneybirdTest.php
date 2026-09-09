<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Actions\PushInvoiceToMoneybirdAction;
use App\Domain\Invoice\Actions\SyncInvoiceStatusAction;
use App\Domain\Invoice\Actions\SyncInvoiceStatusesAction;
use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Invoice\Models\InvoiceLine;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\Moneybird\Models\MoneybirdConnection;
use App\Domain\Moneybird\Services\MoneybirdClient;
use App\Domain\User\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const INVOICE_MONEYBIRD_API = 'moneybird.com/api/v2/123456789';

function invoiceMoneybirdFixture(string $name, int $status = 200): PromiseInterface
{
    return Http::response(file_get_contents(base_path("tests/Fixtures/moneybird/{$name}.json")), $status);
}

function invoiceMoneybirdInvoice(array $attributes = []): Invoice
{
    $invoice = Invoice::factory()->create([
        'user_id' => auth()->id(),
        'client_id' => Client::factory()->create(['name' => 'Acme Corporation', 'email' => 'billing@acme.test', 'address' => "Main Street 1\n1234 AB Amsterdam", 'moneybird_contact_id' => null]),
        'period_starts_on' => '2026-08-01',
        'period_ends_on' => '2026-08-31',
        'subtotal' => '332.50',
        'total' => '332.50',
        'specification' => "Hour specification for Acme Corporation\n\nTotal: 3.50 h",
        ...$attributes,
    ]);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Website redesign · Development',
        'quantity' => '3.50',
        'unit_price' => '95.00',
        'amount' => '332.50',
        'sort_order' => 0,
    ]);

    return $invoice;
}

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->connection = MoneybirdConnection::factory()->for($this->user)->create([
        'access_token' => 'secret-token',
        'administration_id' => '123456789',
        'tax_rate_id' => '433000000000000001',
        'ledger_account_id' => '434000000000000001',
        'workflow_id' => '435000000000000001',
    ]);
});

it('creates the contact when the client is not linked yet and stores the Moneybird ids', function (): void {
    Http::fake([
        INVOICE_MONEYBIRD_API.'/contacts.json?query=*' => invoiceMoneybirdFixture('contacts_empty'),
        INVOICE_MONEYBIRD_API.'/contacts.json' => invoiceMoneybirdFixture('contact_created', 201),
        INVOICE_MONEYBIRD_API.'/sales_invoices.json' => invoiceMoneybirdFixture('sales_invoice_draft', 201),
        INVOICE_MONEYBIRD_API.'/sales_invoices/422000000000000001/notes.json' => invoiceMoneybirdFixture('note_created', 201),
    ]);
    $invoice = invoiceMoneybirdInvoice();

    app(PushInvoiceToMoneybirdAction::class)->handle($invoice);

    $invoice->refresh();
    expect($invoice->moneybird_invoice_id)->toBe('422000000000000001')
        ->and($invoice->moneybird_url)->toBe('https://moneybird.com/123456789/sales_invoices/422000000000000001')
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->number)->toBeNull()
        ->and($invoice->issued_on?->toDateString())->toBe('2026-09-09')
        ->and($invoice->total)->toBe('402.33')
        ->and($invoice->client->moneybird_contact_id)->toBe('411000000000000002');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/contacts.json')
        && $request['contact']['company_name'] === 'Acme Corporation'
        && $request['contact']['send_invoices_to_email'] === 'billing@acme.test'
        && $request['contact']['address1'] === 'Main Street 1'
        && $request['contact']['zipcode'] === '1234AB'
        && $request['contact']['city'] === 'Amsterdam');

    Http::assertSent(function (Request $request): bool {
        if (! str_ends_with($request->url(), '/sales_invoices.json')) {
            return false;
        }

        $payload = $request['sales_invoice'];
        $detail = $payload['details_attributes'][0];

        return $request->hasHeader('Authorization', 'Bearer secret-token')
            && $payload['contact_id'] === '411000000000000002'
            && $payload['reference'] === 'August 2026'
            && $payload['workflow_id'] === '435000000000000001'
            && $detail['description'] === 'Website redesign · Development'
            && $detail['amount'] === '3.50'
            && $detail['price'] === '95.00'
            && $detail['tax_rate_id'] === '433000000000000001'
            && $detail['ledger_account_id'] === '434000000000000001';
    });

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sales_invoices/422000000000000001/notes.json')
        && str_contains($request['note']['note'], 'Hour specification for Acme Corporation'));
});

it('reuses an existing Moneybird contact found by name', function (): void {
    Http::fake([
        INVOICE_MONEYBIRD_API.'/contacts.json?query=*' => invoiceMoneybirdFixture('contacts_found'),
        INVOICE_MONEYBIRD_API.'/sales_invoices.json' => invoiceMoneybirdFixture('sales_invoice_draft', 201),
        INVOICE_MONEYBIRD_API.'/sales_invoices/*/notes.json' => invoiceMoneybirdFixture('note_created', 201),
    ]);
    $invoice = invoiceMoneybirdInvoice();

    app(PushInvoiceToMoneybirdAction::class)->handle($invoice);

    expect($invoice->client->fresh()?->moneybird_contact_id)->toBe('411000000000000001');
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/contacts.json'));
});

it('does not push an invoice twice', function (): void {
    Http::fake();
    $invoice = invoiceMoneybirdInvoice(['moneybird_invoice_id' => '422000000000000001', 'moneybird_url' => 'https://moneybird.com/123456789/sales_invoices/422000000000000001']);

    app(PushInvoiceToMoneybirdAction::class)->handle($invoice);
    $this->post(route('invoices.push', $invoice))->assertRedirect();

    Http::assertNothingSent();
});

it('pushes through the controller and reports Moneybird errors as a toast', function (): void {
    Http::fake([
        INVOICE_MONEYBIRD_API.'/contacts.json?query=*' => invoiceMoneybirdFixture('contacts_found'),
        INVOICE_MONEYBIRD_API.'/sales_invoices.json' => invoiceMoneybirdFixture('error_unprocessable', 422),
    ]);
    $invoice = invoiceMoneybirdInvoice();

    $this->from(route('invoices.show', $invoice))
        ->post(route('invoices.push', $invoice))
        ->assertRedirect(route('invoices.show', $invoice))
        ->assertSessionHas('toast', fn (array $toast) => $toast['type'] === 'error' && str_contains($toast['message'], 'HTTP 422'));

    expect($invoice->fresh()?->moneybird_invoice_id)->toBeNull();
});

it('refuses to push when the invoice owner has not connected Moneybird', function (): void {
    Http::fake();

    app(PushInvoiceToMoneybirdAction::class)->handle(invoiceMoneybirdInvoice(['user_id' => User::factory()->create()->id]));
})->throws(MoneybirdException::class, 'not connected');

it('syncs status, number and dates from Moneybird', function (string $fixture, InvoiceStatus $expected): void {
    Http::fake([INVOICE_MONEYBIRD_API.'/sales_invoices/422000000000000001.json' => invoiceMoneybirdFixture($fixture)]);
    $invoice = invoiceMoneybirdInvoice(['moneybird_invoice_id' => '422000000000000001']);

    app(SyncInvoiceStatusAction::class)->handle($invoice);

    $invoice->refresh();
    expect($invoice->status)->toBe($expected)
        ->and($invoice->number)->toBe('2026-0042')
        ->and($invoice->due_on?->toDateString())->toBe('2026-09-24')
        ->and($invoice->moneybird_url)->toBe('https://moneybird.com/123456789/sales_invoices/422000000000000001');
})->with([
    'open' => ['sales_invoice_open', InvoiceStatus::Open],
    'paid' => ['sales_invoice_paid', InvoiceStatus::Paid],
    'late' => ['sales_invoice_late', InvoiceStatus::Late],
]);

it('syncs through the controller', function (): void {
    Http::fake([INVOICE_MONEYBIRD_API.'/sales_invoices/422000000000000001.json' => invoiceMoneybirdFixture('sales_invoice_paid')]);
    $invoice = invoiceMoneybirdInvoice(['moneybird_invoice_id' => '422000000000000001']);

    $this->from(route('invoices.show', $invoice))
        ->post(route('invoices.sync', $invoice))
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Paid);
});

it('refreshes every pushed invoice that is not settled yet', function (): void {
    Http::fake([INVOICE_MONEYBIRD_API.'/sales_invoices/*.json' => invoiceMoneybirdFixture('sales_invoice_open')]);
    $open = Invoice::factory()->for($this->user)->create(['moneybird_invoice_id' => '422000000000000001', 'status' => InvoiceStatus::Draft]);
    Invoice::factory()->for($this->user)->create(['moneybird_invoice_id' => '422000000000000009', 'status' => InvoiceStatus::Paid]);
    Invoice::factory()->for($this->user)->create(['moneybird_invoice_id' => null]);
    $orphan = Invoice::factory()->for(User::factory())->create(['moneybird_invoice_id' => '422000000000000002', 'status' => InvoiceStatus::Open]); // owner without a connection

    $synced = app(SyncInvoiceStatusesAction::class)->handle();

    expect($synced)->toBe(1)
        ->and($open->fresh()?->status)->toBe(InvoiceStatus::Open)
        ->and($orphan->fresh()?->number)->toBeNull();
    Http::assertSentCount(1);
});

it('runs the sync as a scheduled artisan command', function (): void {
    Http::fake([INVOICE_MONEYBIRD_API.'/sales_invoices/*.json' => invoiceMoneybirdFixture('sales_invoice_open')]);
    Invoice::factory()->for($this->user)->create(['moneybird_invoice_id' => '422000000000000001']);

    $this->artisan('invoices:sync-statuses')->expectsOutputToContain('Refreshed 1 invoice(s)')->assertSuccessful();
});

it('exposes the lookup lists for the integrations page', function (): void {
    Http::fake([
        INVOICE_MONEYBIRD_API.'/tax_rates.json*' => Http::response([['id' => '1', 'name' => '21% BTW']]),
        INVOICE_MONEYBIRD_API.'/ledger_accounts.json' => Http::response([['id' => '2', 'name' => 'Omzet']]),
        INVOICE_MONEYBIRD_API.'/workflows.json' => Http::response([['id' => '3', 'name' => 'Standaard']]),
    ]);
    $client = MoneybirdClient::forUserOrFail($this->user);

    expect($client->taxRates())->toBe([['id' => '1', 'name' => '21% BTW']])
        ->and($client->ledgerAccounts())->toBe([['id' => '2', 'name' => 'Omzet']])
        ->and($client->workflows())->toBe([['id' => '3', 'name' => 'Standaard']]);
});
