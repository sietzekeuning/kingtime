<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Mcp\Servers\KingtimeServer;
use App\Domain\Mcp\Tools\GetUnbilledSummaryTool;
use App\Domain\Mcp\Tools\PrepareInvoiceTool;
use App\Domain\Mcp\Tools\PreviewInvoiceTool;
use App\Domain\Moneybird\Models\MoneybirdConnection;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;

const MCP_MONEYBIRD_API = 'moneybird.com/api/v2/123456789';

/**
 * @param  class-string<Tool>  $tool
 * @param  array<string, mixed>  $arguments
 */
function mcpInvoiceTool(string $tool, array $arguments = []): TestResponse
{
    return KingtimeServer::actingAs(test()->user)->tool($tool, $arguments);
}

function mcpMoneybirdFixture(string $name, int $status = 200): PromiseInterface
{
    return Http::response(file_get_contents(base_path("tests/Fixtures/moneybird/{$name}.json")), $status);
}

function mcpConfigureMoneybird(): void
{
    MoneybirdConnection::factory()->for(test()->user)->create(['access_token' => 'secret-token', 'administration_id' => '123456789']);
}

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-09 10:00:00'));
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['name' => 'Acme Corporation', 'currency' => 'EUR', 'moneybird_contact_id' => '411000000000000001']);
    $this->project = Project::factory()->for($this->client)->create(['name' => 'Website redesign']);
    $this->entries = TimeEntry::factory()->count(2)->for($this->user)->for($this->project)
        ->sequence(['spent_on' => '2026-08-03', 'hours' => '2.50', 'notes' => 'Homepage'], ['spent_on' => '2026-08-04', 'hours' => '1.00', 'notes' => 'Footer'])
        ->create(['hourly_rate' => '95.00']);
});

it('summarises unbilled hours per client', function (): void {
    $beta = Client::factory()->create(['name' => 'Beta BV']);
    $betaProject = Project::factory()->for($beta)->create();
    TimeEntry::factory()->for($this->user)->for($betaProject)->create(['spent_on' => '2026-07-15', 'hours' => '3.00', 'hourly_rate' => null]);
    TimeEntry::factory()->for($this->user)->for($betaProject)->create(['spent_on' => '2026-09-01', 'hours' => '1.00', 'hourly_rate' => '100.00']);
    TimeEntry::factory()->for($this->user)->for($this->project)->billed()->create(['spent_on' => '2026-07-01', 'hours' => '8.00']);
    TimeEntry::factory()->for($this->user)->for($this->project)->running()->create(['spent_on' => '2026-09-09']);
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-02', 'hours' => '1.00', 'is_billable' => false]);

    mcpInvoiceTool(GetUnbilledSummaryTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('clients', 2)
            ->where('clients.0.client', 'Acme Corporation')
            ->where('clients.0.hours', '3.50')
            ->where('clients.0.amount', '332.50')
            ->where('clients.0.entry_count', 2)
            ->where('clients.0.unpriced_entries', 0)
            ->where('clients.0.oldest_date', '2026-08-03')
            ->where('clients.0.newest_date', '2026-08-04')
            ->where('clients.1.client', 'Beta BV')
            ->where('clients.1.hours', '4.00')
            ->where('clients.1.amount', '100.00')
            ->where('clients.1.unpriced_entries', 1)
            ->where('clients.1.oldest_date', '2026-07-15')
            ->where('total_hours', '7.50')
            ->where('total_amount', '432.50')
            ->where('entry_count', 4)
            ->etc());

    mcpInvoiceTool(GetUnbilledSummaryTool::class, ['client_id' => $beta->id, 'until' => '2026-08-31'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('clients', 1)
            ->where('clients.0.client', 'Beta BV')
            ->where('clients.0.hours', '3.00')
            ->etc());
});

it('previews the invoice for a client and the previous month by default', function (): void {
    mcpInvoiceTool(PreviewInvoiceTool::class, ['client_id' => $this->client->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('client.name', 'Acme Corporation')
            ->where('period_starts_on', '2026-08-01')
            ->where('period_ends_on', '2026-08-31')
            ->where('subtotal', '332.50')
            ->where('total_hours', '3.50')
            ->where('entry_count', 2)
            ->where('unpriced_entries', 0)
            ->has('lines', 1)
            ->where('lines.0.description', 'Website redesign')
            ->where('lines.0.quantity', '3.50')
            ->where('lines.0.unit_price', '95.00')
            ->where('lines.0.amount', '332.50')
            ->has('entries', 2)
            ->where('entries.0.notes', 'Homepage')
            ->where('specification_text', fn (string $text) => str_contains($text, 'Hour specification for Acme Corporation'))
            ->etc());

    expect(Invoice::query()->count())->toBe(0);
});

it('finds the client by name and reports ambiguity', function (): void {
    mcpInvoiceTool(PreviewInvoiceTool::class, ['client_name' => 'acme'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('client.id', $this->client->id)->etc());

    Client::factory()->create(['name' => 'Acme Corporation Holding']);

    mcpInvoiceTool(PreviewInvoiceTool::class, ['client_name' => 'Acme Corporation'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('client.id', $this->client->id)->etc());

    mcpInvoiceTool(PreviewInvoiceTool::class, ['client_name' => 'acme'])
        ->assertHasErrors(['Several clients match "acme"', 'Acme Corporation Holding', 'Pass client_id']);

    mcpInvoiceTool(PreviewInvoiceTool::class, ['client_name' => 'Nobody'])
        ->assertHasErrors(['No client matches "Nobody"']);

    mcpInvoiceTool(PreviewInvoiceTool::class, ['client_id' => 999])
        ->assertHasErrors(['No client with id 999']);

    mcpInvoiceTool(PreviewInvoiceTool::class)
        ->assertHasErrors(['Pass client_id or client_name']);
});

it('says when there is nothing to invoice', function (): void {
    mcpInvoiceTool(PreviewInvoiceTool::class, ['client_id' => $this->client->id, 'from' => '2026-07-01', 'to' => '2026-07-31'])
        ->assertHasErrors(['no unbilled billable hours for Acme Corporation between 01-07-2026 and 31-07-2026']);

    mcpInvoiceTool(PrepareInvoiceTool::class, ['client_id' => $this->client->id, 'from' => '2026-07-01', 'to' => '2026-07-31'])
        ->assertHasErrors(['no unbilled billable hours']);

    expect(Invoice::query()->count())->toBe(0);
});

it('prepares a local draft invoice and locks the hours', function (): void {
    Http::fake();

    mcpInvoiceTool(PrepareInvoiceTool::class, ['client_name' => 'Acme', 'notes' => 'Thanks!'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('message', 'Draft invoice created locally. It has not been pushed to Moneybird.')
            ->where('pushed_to_moneybird', false)
            ->where('moneybird_error', null)
            ->where('invoice.status', 'draft')
            ->where('invoice.client', 'Acme Corporation')
            ->where('invoice.period_starts_on', '2026-08-01')
            ->where('invoice.period_ends_on', '2026-08-31')
            ->where('invoice.subtotal', '332.50')
            ->where('invoice.total_hours', '3.50')
            ->where('invoice.entry_count', 2)
            ->where('invoice.notes', 'Thanks!')
            ->has('invoice.lines', 1)
            ->where('invoice.moneybird_url', null)
            ->etc());

    $invoice = Invoice::query()->firstOrFail();
    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->moneybird_invoice_id)->toBeNull()
        ->and(TimeEntry::query()->where('invoice_id', $invoice->id)->where('is_billed', true)->count())->toBe(2);
    Http::assertNothingSent();
});

it('prepares an invoice for selected entries only', function (): void {
    $first = $this->entries->first();

    mcpInvoiceTool(PrepareInvoiceTool::class, ['client_id' => $this->client->id, 'from' => '2026-08-01', 'to' => '2026-08-31', 'time_entry_ids' => [$first->id]])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('invoice.subtotal', '237.50')
            ->where('invoice.entry_count', 1)
            ->etc());

    expect($first->fresh()?->is_billed)->toBeTrue()
        ->and($this->entries->last()->fresh()?->is_billed)->toBeFalse();
});

it('pushes the draft to Moneybird when asked', function (): void {
    mcpConfigureMoneybird();
    Http::fake([
        MCP_MONEYBIRD_API.'/sales_invoices.json' => mcpMoneybirdFixture('sales_invoice_draft', 201),
        MCP_MONEYBIRD_API.'/sales_invoices/422000000000000001/notes.json' => mcpMoneybirdFixture('note_created', 201),
    ]);

    mcpInvoiceTool(PrepareInvoiceTool::class, ['client_id' => $this->client->id, 'push_to_moneybird' => true])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('pushed_to_moneybird', true)
            ->where('moneybird_error', null)
            ->where('invoice.moneybird_invoice_id', '422000000000000001')
            ->where('invoice.moneybird_url', 'https://moneybird.com/123456789/sales_invoices/422000000000000001')
            ->where('invoice.status', 'draft')
            ->etc());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sales_invoices.json')
        && $request['sales_invoice']['contact_id'] === '411000000000000001'
        && $request['sales_invoice']['reference'] === 'August 2026');
});

it('keeps the local draft when the Moneybird push fails', function (): void {
    mcpConfigureMoneybird();
    Http::fake([
        MCP_MONEYBIRD_API.'/sales_invoices.json' => mcpMoneybirdFixture('error_unprocessable', 422),
    ]);

    mcpInvoiceTool(PrepareInvoiceTool::class, ['client_id' => $this->client->id, 'push_to_moneybird' => true])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('pushed_to_moneybird', false)
            ->where('moneybird_error', fn (string $error) => str_contains($error, 'HTTP 422'))
            ->where('invoice.moneybird_url', null)
            ->etc());

    expect(Invoice::query()->count())->toBe(1);
});

it('reports a missing Moneybird connection instead of failing', function (): void {
    Http::fake();

    mcpInvoiceTool(PrepareInvoiceTool::class, ['client_id' => $this->client->id, 'push_to_moneybird' => true])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('pushed_to_moneybird', false)
            ->where('moneybird_error', fn (string $error) => str_contains($error, 'not connected'))
            ->etc());

    Http::assertNothingSent();
});
