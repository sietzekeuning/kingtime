<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Actions\DeleteInvoiceAction;
use App\Domain\Invoice\Actions\PrepareInvoiceAction;
use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Invoice\Exceptions\InvoiceLockedException;
use App\Domain\Invoice\Exceptions\NothingToInvoiceException;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Moneybird\Models\MoneybirdConnection;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->client = Client::factory()->create(['name' => 'Acme Corporation', 'currency' => 'EUR']);
    $this->project = Project::factory()->for($this->client)->create(['name' => 'Website redesign']);
});

it('prepares a draft invoice and locks the entries to it', function (): void {
    $entries = TimeEntry::factory()->count(2)->for($this->user)->for($this->project)
        ->sequence(['spent_on' => '2026-08-03', 'hours' => '2.50'], ['spent_on' => '2026-08-04', 'hours' => '1.00'])
        ->create(['hourly_rate' => '95.00']);

    $invoice = app(PrepareInvoiceAction::class)->handle($this->user, $this->client, Date::parse('2026-08-01'), Date::parse('2026-08-31'), null, '  Thanks!  ');

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->currency)->toBe('EUR')
        ->and($invoice->subtotal)->toBe('332.50')
        ->and($invoice->total)->toBe('332.50')
        ->and($invoice->notes)->toBe('Thanks!')
        ->and($invoice->specification)->toContain('Hour specification for Acme Corporation')
        ->and($invoice->period_starts_on?->toDateString())->toBe('2026-08-01')
        ->and($invoice->period_ends_on?->toDateString())->toBe('2026-08-31')
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first()?->description)->toBe('Website redesign')
        ->and($invoice->lines->first()?->quantity)->toBe('3.50')
        ->and($invoice->lines->first()?->project_id)->toBe($this->project->id);

    foreach ($entries as $entry) {
        $entry->refresh();
        expect($entry->invoice_id)->toBe($invoice->id)
            ->and($entry->is_billed)->toBeTrue()
            ->and($entry->is_locked)->toBeTrue();
    }
});

it('refuses to prepare an invoice without hours', function (): void {
    app(PrepareInvoiceAction::class)->handle($this->user, $this->client, Date::parse('2026-08-01'), Date::parse('2026-08-31'));
})->throws(NothingToInvoiceException::class);

it('deletes a draft and unlocks its entries', function (): void {
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-08-03']);
    $invoice = app(PrepareInvoiceAction::class)->handle($this->user, $this->client, Date::parse('2026-08-01'), Date::parse('2026-08-31'));
    $entry = TimeEntry::query()->firstOrFail();

    $this->delete(route('invoices.destroy', $invoice))->assertRedirect(route('invoices.index'));

    $entry->refresh();
    expect(Invoice::query()->find($invoice->id))->toBeNull()
        ->and($entry->invoice_id)->toBeNull()
        ->and($entry->is_billed)->toBeFalse()
        ->and($entry->is_locked)->toBeFalse();
});

it('refuses to delete a pushed invoice unless forced', function (): void {
    $invoice = Invoice::factory()->create(['moneybird_invoice_id' => '422000000000000001']);

    expect(fn () => app(DeleteInvoiceAction::class)->handle($invoice))->toThrow(InvoiceLockedException::class);

    $this->delete(route('invoices.destroy', $invoice))->assertRedirect();
    expect(Invoice::query()->find($invoice->id))->not->toBeNull();

    $this->delete(route('invoices.destroy', [$invoice, 'force' => 1]))->assertRedirect(route('invoices.index'));
    expect(Invoice::query()->find($invoice->id))->toBeNull();
});

it('refuses to delete a non-draft invoice', function (): void {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Open]);

    app(DeleteInvoiceAction::class)->handle($invoice);
})->throws(InvoiceLockedException::class);

it('shows the prepare form without a preview until a client is chosen', function (): void {
    $this->get(route('invoices.prepare.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/InvoicePrepare')
            ->has('clients', 1)
            ->where('clients.0.name', 'Acme Corporation')
            ->where('specification', null)
            ->where('filters.client_id', null)
            ->where('filters.period_starts_on', now()->subMonthNoOverflow()->startOfMonth()->toDateString()));
});

it('previews the specification for a client and period', function (): void {
    $first = TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-08-03', 'hours' => '2.00', 'hourly_rate' => '95.00']);
    $second = TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-08-04', 'hours' => '1.00', 'hourly_rate' => '95.00']);

    $this->get(route('invoices.prepare.create', ['client_id' => $this->client->id, 'period_starts_on' => '2026-08-01', 'period_ends_on' => '2026-08-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('specification.subtotal', '285.00')
            ->has('specification.lines', 1)
            ->has('specification.entries', 2)
            ->has('available_entries', 2));

    $this->get(route('invoices.prepare.create', ['client_id' => $this->client->id, 'period_starts_on' => '2026-08-01', 'period_ends_on' => '2026-08-31', 'time_entry_ids' => [$first->id]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('specification.subtotal', '190.00')
            ->has('specification.entries', 1)
            ->where('specification.entries.0.id', $first->id)
            ->has('available_entries', 2)
            ->where('available_entries.1.id', $second->id));
});

it('validates the prepare form', function (): void {
    $this->from(route('invoices.prepare.create'))
        ->post(route('invoices.prepare.store'), [
            'client_id' => 999,
            'period_starts_on' => '2026-08-31',
            'period_ends_on' => '2026-08-01',
            'time_entry_ids' => ['abc'],
        ])
        ->assertRedirect(route('invoices.prepare.create'))
        ->assertSessionHasErrors(['client_id', 'period_ends_on', 'time_entry_ids.0']);

    $this->from(route('invoices.prepare.create'))
        ->post(route('invoices.prepare.store'), ['client_id' => $this->client->id, 'period_starts_on' => '2026-08-01', 'period_ends_on' => '2026-08-31'])
        ->assertRedirect(route('invoices.prepare.create'))
        ->assertSessionHasErrors(['period_starts_on']);
});

it('creates the draft from the form and redirects to it', function (): void {
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-08-03']);

    $response = $this->post(route('invoices.prepare.store'), [
        'client_id' => $this->client->id,
        'period_starts_on' => '2026-08-01',
        'period_ends_on' => '2026-08-31',
        'notes' => 'Please pay within 14 days.',
    ]);

    $invoice = Invoice::query()->firstOrFail();
    $response->assertRedirect(route('invoices.show', $invoice));
    expect($invoice->notes)->toBe('Please pay within 14 days.')
        ->and($invoice->moneybird_invoice_id)->toBeNull();
});

it('pushes the fresh draft to Moneybird when asked', function (): void {
    MoneybirdConnection::factory()->for($this->user)->create(['administration_id' => '123456789']);
    Http::fake([
        'moneybird.com/api/v2/123456789/contacts.json*' => Http::response(file_get_contents(base_path('tests/Fixtures/moneybird/contacts_found.json')), 200),
        'moneybird.com/api/v2/123456789/sales_invoices.json' => Http::response(file_get_contents(base_path('tests/Fixtures/moneybird/sales_invoice_draft.json')), 201),
        'moneybird.com/api/v2/123456789/sales_invoices/*/notes.json' => Http::response(file_get_contents(base_path('tests/Fixtures/moneybird/note_created.json')), 201),
    ]);
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-08-03']);

    $this->post(route('invoices.prepare.store'), [
        'client_id' => $this->client->id,
        'period_starts_on' => '2026-08-01',
        'period_ends_on' => '2026-08-31',
        'push_to_moneybird' => true,
    ])->assertRedirect();

    expect(Invoice::query()->firstOrFail()->moneybird_invoice_id)->toBe('422000000000000001');
});
