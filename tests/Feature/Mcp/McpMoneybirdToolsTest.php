<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Mcp\Servers\KingtimeServer;
use App\Domain\Mcp\Tools\MoneybirdGetSalesInvoiceTool;
use App\Domain\Mcp\Tools\MoneybirdGetTool;
use App\Domain\Mcp\Tools\MoneybirdListContactsTool;
use App\Domain\Mcp\Tools\MoneybirdListPurchaseInvoicesTool;
use App\Domain\Mcp\Tools\MoneybirdListReceiptsTool;
use App\Domain\Mcp\Tools\MoneybirdListSalesInvoicesTool;
use App\Domain\Mcp\Tools\MoneybirdRevenueSummaryTool;
use App\Domain\Mcp\Tools\MoneybirdStatusTool;
use App\Domain\User\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;

const MCP_MB_API = 'moneybird.com/api/v2/123456789';

/**
 * @param  class-string<Tool>  $tool
 * @param  array<string, mixed>  $arguments
 */
function mcpMbTool(string $tool, array $arguments = []): TestResponse
{
    return KingtimeServer::actingAs(test()->user)->tool($tool, $arguments);
}

function mcpMbFixture(string $name, int $status = 200): PromiseInterface
{
    return Http::response(file_get_contents(base_path("tests/Fixtures/moneybird/{$name}.json")), $status);
}

/**
 * The query string of a faked request as an array.
 *
 * @return array<string, string>
 */
function mcpMbQuery(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    /** @var array<string, string> $query */
    return $query;
}

beforeEach(function (): void {
    $this->user = User::factory()->create();
    config()->set('services.moneybird', [
        'access_token' => 'secret-token',
        'administration_id' => '123456789',
        'base_url' => 'https://moneybird.com/api/v2',
        'tax_rate_id' => null,
        'ledger_account_id' => null,
        'workflow_id' => null,
    ]);
});

it('reports the configured administration', function (): void {
    Http::fake(['moneybird.com/api/v2/administrations.json' => mcpMbFixture('administrations')]);

    mcpMbTool(MoneybirdStatusTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('configured', true)
            ->where('reachable', true)
            ->where('administration_id', '123456789')
            ->where('name', 'King Websites')
            ->where('currency', 'EUR')
            ->where('language', 'nl')
            ->etc());
});

it('reports a missing configuration in the status and as an error in the other tools', function (): void {
    config()->set('services.moneybird.access_token', null);
    Http::fake();

    mcpMbTool(MoneybirdStatusTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('configured', false)
            ->where('message', fn (string $message) => str_contains($message, 'MONEYBIRD_ACCESS_TOKEN and MONEYBIRD_ADMINISTRATION_ID'))
            ->etc());

    mcpMbTool(MoneybirdListSalesInvoicesTool::class)
        ->assertHasErrors(['Moneybird is not configured', 'MONEYBIRD_ACCESS_TOKEN']);

    mcpMbTool(MoneybirdGetTool::class, ['path' => 'contacts'])
        ->assertHasErrors(['Moneybird is not configured']);

    Http::assertNothingSent();
});

it('says when the token cannot see the configured administration', function (): void {
    config()->set('services.moneybird.administration_id', '42');
    Http::fake(['moneybird.com/api/v2/administrations.json' => mcpMbFixture('administrations')]);

    mcpMbTool(MoneybirdStatusTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('configured', true)
            ->where('reachable', false)
            ->where('administration_id', '42')
            ->etc());
});

it('lists contacts and marks the linked Kingtime client', function (): void {
    Client::factory()->create(['name' => 'Acme Corporation', 'moneybird_contact_id' => '411000000000000001']);
    Http::fake([MCP_MB_API.'/contacts.json*' => mcpMbFixture('contacts_list')]);

    mcpMbTool(MoneybirdListContactsTool::class, ['query' => 'acme', 'page' => 2])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('page', 2)
            ->where('count', 2)
            ->where('has_more', false)
            ->has('contacts', 2)
            ->where('contacts.0.id', '411000000000000001')
            ->where('contacts.0.name', 'Acme Corporation')
            ->where('contacts.0.company_name', 'Acme Corporation')
            ->where('contacts.0.email', 'billing@acme.test')
            ->where('contacts.0.customer_id', '1')
            ->where('contacts.0.kingtime_client', 'Acme Corporation')
            ->where('contacts.1.name', 'Jan Jansen')
            ->where('contacts.1.firstname', 'Jan')
            ->where('contacts.1.company_name', null)
            ->where('contacts.1.kingtime_client_id', null)
            ->etc());

    Http::assertSent(fn (Request $request) => mcpMbQuery($request) === ['page' => '2', 'per_page' => '50', 'query' => 'acme']);
});

it('lists sales invoices with Moneybird filters and page totals', function (): void {
    Http::fake([MCP_MB_API.'/sales_invoices.json*' => mcpMbFixture('sales_invoices_list')]);

    mcpMbTool(MoneybirdListSalesInvoicesTool::class, ['state' => 'open', 'period' => 'prev_month', 'contact_id' => '411000000000000001', 'per_page' => 10])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('filters', ['period' => 'prev_month', 'contact_id' => '411000000000000001', 'state' => 'open'])
            ->where('page', 1)
            ->where('per_page', 10)
            ->where('count', 2)
            ->where('has_more', false)
            ->where('total_excl_tax', '832.50')
            ->where('total_incl_tax', '1007.33')
            ->where('total_unpaid', '507.33')
            ->has('invoices', 2)
            ->where('invoices.0.id', '422000000000000001')
            ->where('invoices.0.invoice_id', '2026-0042')
            ->where('invoices.0.contact', 'Acme Corporation')
            ->where('invoices.0.invoice_date', '2026-08-31')
            ->where('invoices.0.due_date', '2026-09-14')
            ->where('invoices.0.state', 'open')
            ->where('invoices.0.total_price_excl_tax', '332.50')
            ->where('invoices.0.total_price_incl_tax', '402.33')
            ->where('invoices.0.total_unpaid', '402.33')
            ->where('invoices.0.currency', 'EUR')
            ->where('invoices.0.url', 'https://moneybird.com/123456789/sales_invoices/422000000000000001')
            ->where('invoices.1.contact', 'Beta BV')
            ->where('invoices.1.state', 'late')
            ->etc());

    Http::assertSent(fn (Request $request) => mcpMbQuery($request) === [
        'page' => '1',
        'per_page' => '10',
        'filter' => 'period:prev_month,contact_id:411000000000000001,state:open',
    ]);
});

it('rejects an unknown state or period', function (): void {
    Http::fake();

    mcpMbTool(MoneybirdListSalesInvoicesTool::class, ['state' => 'sent'])->assertHasErrors();
    mcpMbTool(MoneybirdListSalesInvoicesTool::class, ['period' => 'last week'])->assertHasErrors();
    mcpMbTool(MoneybirdListSalesInvoicesTool::class, ['period' => '202601..202603'])->assertOk();
    mcpMbTool(MoneybirdListSalesInvoicesTool::class, ['period' => '20260101..20260131'])->assertOk();
});

it('returns one sales invoice with lines, payments and notes', function (): void {
    Http::fake([MCP_MB_API.'/sales_invoices/422000000000000001.json' => mcpMbFixture('sales_invoice_detail')]);

    mcpMbTool(MoneybirdGetSalesInvoiceTool::class, ['id' => '422000000000000001'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', '422000000000000001')
            ->where('invoice_id', '2026-0042')
            ->where('contact', 'Acme Corporation')
            ->where('contact_email', 'acme@example.test')
            ->where('state', 'paid')
            ->where('reference', 'August 2026')
            ->where('paid_at', '2026-09-10')
            ->where('total_price_excl_tax', '332.50')
            ->where('total_tax', '69.83')
            ->where('total_unpaid', '0.00')
            ->where('url', 'https://moneybird.com/123456789/sales_invoices/422000000000000001')
            ->has('lines', 1)
            ->where('lines.0.description', 'Website redesign · Development')
            ->where('lines.0.amount', '3.5 x')
            ->where('lines.0.quantity', '3.50')
            ->where('lines.0.price', '95.00')
            ->where('lines.0.total', '332.50')
            ->has('payments', 1)
            ->where('payments.0.payment_date', '2026-09-10')
            ->where('payments.0.price', '402.33')
            ->has('notes', 1)
            ->where('notes.0.note', 'Hour specification for Acme Corporation')
            ->etc());

    mcpMbTool(MoneybirdGetSalesInvoiceTool::class, ['id' => '2026-0042'])->assertHasErrors();
});

it('lists purchase invoices and receipts', function (): void {
    Http::fake([
        MCP_MB_API.'/documents/purchase_invoices.json*' => mcpMbFixture('purchase_invoices_list'),
        MCP_MB_API.'/documents/receipts.json*' => mcpMbFixture('receipts_list'),
    ]);

    mcpMbTool(MoneybirdListPurchaseInvoicesTool::class, ['period' => 'this_month'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('filters', ['period' => 'this_month'])
            ->where('count', 2)
            ->where('total_excl_tax', '1249.00')
            ->where('total_incl_tax', '1511.29')
            ->where('purchase_invoices.0.id', '426000000000000001')
            ->where('purchase_invoices.0.reference', 'INV-2026-0815')
            ->where('purchase_invoices.0.contact', 'Hosting Provider')
            ->where('purchase_invoices.0.date', '2026-08-15')
            ->where('purchase_invoices.0.due_date', '2026-08-29')
            ->where('purchase_invoices.0.state', 'open')
            ->where('purchase_invoices.0.total_price_incl_tax', '59.29')
            ->where('purchase_invoices.0.url', 'https://moneybird.com/123456789/documents/426000000000000001')
            ->etc());

    mcpMbTool(MoneybirdListReceiptsTool::class, ['contact_id' => '411000000000000012'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('filters', ['contact_id' => '411000000000000012'])
            ->where('count', 1)
            ->where('receipts.0.reference', 'Bon 1188')
            ->where('receipts.0.contact', 'Coffee Bar')
            ->where('receipts.0.due_date', null)
            ->where('receipts.0.total_price_excl_tax', '4.13')
            ->where('receipts.0.url', 'https://moneybird.com/123456789/documents/427000000000000001')
            ->etc());

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'purchase_invoices') && mcpMbQuery($request)['filter'] === 'period:this_month');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'receipts') && mcpMbQuery($request)['filter'] === 'contact_id:411000000000000012');
});

it('sums revenue per month and per contact across pages, leaving drafts out', function (): void {
    Http::fake([
        MCP_MB_API.'/sales_invoices.json*' => fn (Request $request) => mcpMbQuery($request)['page'] === '2'
            ? mcpMbFixture('sales_invoices_page2')
            : mcpMbFixture('sales_invoices_page1'),
    ]);

    mcpMbTool(MoneybirdRevenueSummaryTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('period', 'this_year')
            ->where('invoice_count', 100)
            ->where('excluded_count', 1)
            ->where('currencies', ['EUR'])
            ->where('total_excl_tax', '2052.50')
            ->where('total_unpaid', '704.83')
            ->where('capped', false)
            ->where('note', null)
            ->has('per_month', 3)
            ->where('per_month.0.month', '2026-07')
            ->where('per_month.0.total_excl_tax', '970.00')
            ->where('per_month.0.invoice_count', 97)
            ->where('per_month.1.month', '2026-08')
            ->where('per_month.1.total_excl_tax', '832.50')
            ->where('per_month.1.total_unpaid', '402.33')
            ->where('per_month.1.invoice_count', 2)
            ->where('per_month.2.month', '2026-09')
            ->where('per_month.2.total_excl_tax', '250.00')
            ->where('per_month.2.total_unpaid', '302.50')
            ->where('per_month.2.invoice_count', 1)
            ->has('per_contact', 3)
            ->where('per_contact.0.contact', 'Filler Holding')
            ->where('per_contact.0.total_excl_tax', '970.00')
            ->where('per_contact.1.contact_id', '411000000000000002')
            ->where('per_contact.1.contact', 'Beta BV')
            ->where('per_contact.1.total_excl_tax', '750.00')
            ->where('per_contact.1.total_unpaid', '302.50')
            ->where('per_contact.1.invoice_count', 2)
            ->where('per_contact.2.contact', 'Acme Corporation')
            ->where('per_contact.2.total_excl_tax', '332.50')
            ->where('per_contact.2.total_unpaid', '402.33')
            ->where('per_contact.2.invoice_count', 1)
            ->etc());

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => mcpMbQuery($request) === ['page' => '1', 'per_page' => '100', 'filter' => 'period:this_year,state:all']);
    Http::assertSent(fn (Request $request) => mcpMbQuery($request)['page'] === '2');
});

it('passes the period on to the revenue summary', function (): void {
    Http::fake([MCP_MB_API.'/sales_invoices.json*' => mcpMbFixture('sales_invoices_page2')]);

    mcpMbTool(MoneybirdRevenueSummaryTool::class, ['period' => 'prev_quarter'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('period', 'prev_quarter')->where('invoice_count', 1)->etc());

    Http::assertSent(fn (Request $request) => mcpMbQuery($request)['filter'] === 'period:prev_quarter,state:all');
});

it('reads any GET endpoint through moneybird_get', function (): void {
    Http::fake([
        MCP_MB_API.'/sales_invoices/422000000000000001/payments.json*' => mcpMbFixture('payments_list'),
        MCP_MB_API.'/contacts/411000000000000001.json' => mcpMbFixture('contact_detail'),
    ]);

    mcpMbTool(MoneybirdGetTool::class, ['path' => '/sales_invoices/422000000000000001/payments.json', 'query' => ['page' => 1, 'per_page' => '25', 'filter' => 'period:this_year']])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('path', 'sales_invoices/422000000000000001/payments')
            ->where('query', ['page' => 1, 'per_page' => '25', 'filter' => 'period:this_year'])
            ->where('count', 1)
            ->where('result.0.id', '424000000000000001')
            ->where('result.0.price', '402.33')
            ->etc());

    Http::assertSent(fn (Request $request) => $request->url() === 'https://moneybird.com/api/v2/123456789/sales_invoices/422000000000000001/payments.json?page=1&per_page=25&filter=period%3Athis_year');

    mcpMbTool(MoneybirdGetTool::class, ['path' => 'contacts/411000000000000001'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('query', null)
            ->where('count', null)
            ->where('result.company_name', 'Acme Corporation')
            ->etc());
});

it('rejects unsafe paths and query values in moneybird_get', function (): void {
    Http::fake();

    mcpMbTool(MoneybirdGetTool::class, ['path' => '../other_admin/contacts'])->assertHasErrors(['Invalid path']);
    mcpMbTool(MoneybirdGetTool::class, ['path' => 'Contacts'])->assertHasErrors(['Invalid path']);
    mcpMbTool(MoneybirdGetTool::class, ['path' => 'contacts?query=x'])->assertHasErrors(['Invalid path']);
    mcpMbTool(MoneybirdGetTool::class, ['path' => 'contacts//1'])->assertHasErrors(['Invalid path']);
    mcpMbTool(MoneybirdGetTool::class, ['path' => ''])->assertHasErrors();
    mcpMbTool(MoneybirdGetTool::class, ['path' => 'contacts', 'query' => ['filter' => ['nested']]])->assertHasErrors(['must be a string or number']);
    mcpMbTool(MoneybirdGetTool::class, ['path' => 'contacts', 'query' => ['bad key' => 'x']])->assertHasErrors(['Invalid query parameter name']);

    Http::assertNothingSent();
});

it('returns the Moneybird message when the API fails', function (): void {
    Http::fake([
        MCP_MB_API.'/sales_invoices.json*' => mcpMbFixture('error_unprocessable', 422),
        MCP_MB_API.'/contacts.json*' => Http::response('', 401),
    ]);

    mcpMbTool(MoneybirdListSalesInvoicesTool::class)
        ->assertHasErrors(['Moneybird GET sales_invoices failed with HTTP 422', 'contact_id']);

    mcpMbTool(MoneybirdListContactsTool::class)
        ->assertHasErrors(['Moneybird GET contacts failed with HTTP 401']);
});
