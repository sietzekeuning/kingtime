<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Actions\PrepareInvoiceAction;
use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    config()->set('services.moneybird.access_token', null);
    config()->set('services.moneybird.administration_id', null);
});

it('lists invoices with the table payload', function (): void {
    Invoice::factory()->count(2)->create();
    Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'number' => '2026-0042']);

    $this->get(route('invoices.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/InvoiceList')
            ->has('items.data', 3)
            ->has('items.allowed_sorts')
            ->has('items.allowed_filters'));

    $this->get(route('invoices.index', ['filter' => ['status' => 'paid']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.number', '2026-0042'));
});

it('shows an invoice with its lines, entries and specification', function (): void {
    $client = Client::factory()->create(['name' => 'Acme Corporation']);
    $project = Project::factory()->for($client)->create(['name' => 'Website redesign']);
    TimeEntry::factory()->for($this->user)->for($project)->create(['spent_on' => '2026-08-03', 'hours' => '2.00', 'hourly_rate' => '95.00']);
    $invoice = app(PrepareInvoiceAction::class)->handle($client, Date::parse('2026-08-01'), Date::parse('2026-08-31'));

    $this->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/InvoiceShow')
            ->where('invoice.id', $invoice->id)
            ->where('invoice.client_name', 'Acme Corporation')
            ->where('invoice.status', 'draft')
            ->where('invoice.total_hours', '2.00')
            ->has('invoice.lines', 1)
            ->where('invoice.lines.0.project_name', 'Website redesign')
            ->has('invoice.time_entries', 1)
            ->where('invoice.time_entries.0.project_name', 'Website redesign')
            ->where('invoice.time_entries_count', 1)
            ->whereType('invoice.specification', 'string')
            ->where('moneybird_configured', false));
});

it('redirects guests', function (): void {
    auth()->logout();

    $this->get(route('invoices.index'))->assertRedirect(route('login'));
});
