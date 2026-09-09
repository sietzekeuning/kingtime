<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Actions\PrepareInvoiceAction;
use App\Domain\Invoice\Actions\RenderInvoicePdfAction;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'name' => 'Sietze Keuning',
        'email' => 'info@example.test',
        'company_name' => 'King Websites',
        'company_address' => "Hoofdstraat 1\n9000 AA Groningen",
        'vat_number' => 'NL123456789B01',
        'coc_number' => '12345678',
        'iban' => 'NL00BANK0123456789',
    ]);
    $this->actingAs($this->user);

    $client = Client::factory()->create(['name' => 'Acme Corporation', 'address' => "Main Street 5\nAmsterdam"]);
    $website = Project::factory()->for($client)->create(['name' => 'Website redesign']);
    $support = Project::factory()->for($client)->create(['name' => 'Support']);
    TimeEntry::factory()->for($this->user)->for($website)->create(['spent_on' => '2026-08-03', 'hours' => '2.50', 'hourly_rate' => '95.00', 'notes' => 'Homepage hero']);
    TimeEntry::factory()->for($this->user)->for($website)->create(['spent_on' => '2026-08-04', 'hours' => '1.00', 'hourly_rate' => '95.00', 'notes' => 'Footer']);
    TimeEntry::factory()->for($this->user)->for($support)->create(['spent_on' => '2026-08-10', 'hours' => '0.50', 'hourly_rate' => '110.00', 'notes' => 'Phone call']);

    $this->invoice = app(PrepareInvoiceAction::class)->handle($this->user, $client, Date::parse('2026-08-01'), Date::parse('2026-08-31'), notes: 'Thanks for your business.');
});

it('renders the invoice html with the sender, the client, the lines and the specification', function (): void {
    $html = app(RenderInvoicePdfAction::class)->html($this->invoice);

    expect($html)
        ->toContain("Draft invoice #{$this->invoice->id}")
        ->toContain('King Websites')
        ->toContain('Sietze Keuning')
        ->toContain('Hoofdstraat 1')
        ->toContain('VAT NL123456789B01')
        ->toContain('CoC 12345678')
        ->toContain('NL00BANK0123456789')
        ->toContain('Acme Corporation')
        ->toContain('Main Street 5')
        ->toContain('August 2026')
        ->toContain('Website redesign')
        ->toContain('Support')
        ->toContain('3,50')
        ->toContain('332,50')
        ->toContain('55,00')
        ->toContain('387,50')
        ->toContain('Homepage hero')
        ->toContain('03-08-2026')
        ->toContain('Thanks for your business.')
        ->not->toContain('Total (incl. VAT)');
});

it('shows the moneybird number, dates and vat total once the invoice is pushed', function (): void {
    $this->invoice->update(['number' => '2026-0042', 'issued_on' => '2026-09-01', 'due_on' => '2026-09-15', 'total' => '468.88']);

    $html = app(RenderInvoicePdfAction::class)->html($this->invoice->fresh());

    expect($html)
        ->toContain('Invoice 2026-0042')
        ->toContain('01-09-2026')
        ->toContain('15-09-2026')
        ->toContain('Total (incl. VAT)')
        ->toContain('468,88')
        ->not->toContain('class="badge"');
});

it('falls back to the account name and email when there are no invoice details', function (): void {
    $this->user->update(['company_name' => null, 'company_address' => null, 'vat_number' => null, 'coc_number' => null, 'iban' => null]);

    $html = app(RenderInvoicePdfAction::class)->html($this->invoice->fresh());

    expect($html)
        ->toContain('Sietze Keuning')
        ->toContain('info@example.test')
        ->not->toContain('VAT ')
        ->not->toContain('Please transfer');
});

it('streams the pdf from the controller', function (): void {
    $this->mock(RenderInvoicePdfAction::class)
        ->shouldReceive('handle')
        ->once()
        ->withArgs(fn (Invoice $invoice) => $invoice->is($this->invoice))
        ->andReturn('%PDF-1.4 fake');

    $this->get(route('invoices.pdf', $this->invoice))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="draft-invoice-'.$this->invoice->id.'.pdf"')
        ->assertContent('%PDF-1.4 fake');
});

it('explains when chrome is not available instead of crashing', function (): void {
    $this->mock(RenderInvoicePdfAction::class)
        ->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('node: command not found'));

    $this->from(route('invoices.show', $this->invoice))
        ->get(route('invoices.pdf', $this->invoice))
        ->assertRedirect(route('invoices.show', $this->invoice))
        ->assertSessionHas('toast.type', 'error');
});

it('hides other users\' invoices', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(route('invoices.pdf', $this->invoice))->assertNotFound();
});
