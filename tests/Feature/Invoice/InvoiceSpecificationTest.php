<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Actions\BuildInvoiceSpecificationAction;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['name' => 'Acme Corporation']);
    $this->from = Date::parse('2026-08-01');
    $this->to = Date::parse('2026-08-31');
});

it('groups entries per project and rate with summed hours', function (): void {
    $website = Project::factory()->for($this->client)->create(['name' => 'Website redesign']);
    $app = Project::factory()->for($this->client)->create(['name' => 'App']);

    TimeEntry::factory()->for($this->user)->for($website)->create(['spent_on' => '2026-08-03', 'hours' => '2.50', 'hourly_rate' => '95.00', 'notes' => 'Homepage']);
    TimeEntry::factory()->for($this->user)->for($website)->create(['spent_on' => '2026-08-04', 'hours' => '1.00', 'hourly_rate' => '95.00', 'notes' => 'Footer']);
    TimeEntry::factory()->for($this->user)->for($website)->create(['spent_on' => '2026-08-04', 'hours' => '0.50', 'hourly_rate' => '95.00', 'notes' => null]);
    TimeEntry::factory()->for($this->user)->for($website)->create(['spent_on' => '2026-08-10', 'hours' => '1.00', 'hourly_rate' => '110.00']);
    TimeEntry::factory()->for($this->user)->for($app)->create(['spent_on' => '2026-08-12', 'hours' => '4.00', 'hourly_rate' => '80.00']);

    $specification = app(BuildInvoiceSpecificationAction::class)->handle($this->client, $this->from, $this->to);

    expect($specification->lines)->toHaveCount(3)
        ->and($specification->lines->map(fn ($line) => [$line->description, $line->quantity, $line->unit_price, $line->amount])->all())->toBe([
            ['App', '4.00', '80.00', '320.00'],
            ['Website redesign', '1.00', '110.00', '110.00'],
            ['Website redesign', '4.00', '95.00', '380.00'],
        ])
        ->and($specification->lines->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and($specification->subtotal)->toBe('810.00')
        ->and($specification->total_hours)->toBe('9.00')
        ->and($specification->entries)->toHaveCount(5)
        ->and($specification->unpriced_entries)->toBe(0)
        ->and($specification->client->name)->toBe('Acme Corporation')
        ->and($specification->specification_text)->toContain('Period: August 2026')
        ->toContain("## Website redesign\n03-08-2026    2.50 h  Homepage\n04-08-2026    1.00 h  Footer\n04-08-2026    0.50 h\n")
        ->toContain('Subtotal Website redesign: 5.00 h')
        ->toContain("## App\n12-08-2026    4.00 h")
        ->toContain('Total: 9.00 h');
});

it('flags entries without a rate and still lists their hours', function (): void {
    $project = Project::factory()->for($this->client)->create(['name' => 'Support']);
    TimeEntry::factory()->for($this->user)->for($project)->create(['spent_on' => '2026-08-05', 'hours' => '2.00', 'hourly_rate' => null]);
    TimeEntry::factory()->for($this->user)->for($project)->create(['spent_on' => '2026-08-06', 'hours' => '1.00', 'hourly_rate' => '50.00']);

    $specification = app(BuildInvoiceSpecificationAction::class)->handle($this->client, $this->from, $this->to);

    expect($specification->unpriced_entries)->toBe(1)
        ->and($specification->lines->map(fn ($line) => [$line->quantity, $line->unit_price, $line->amount])->all())->toBe([
            ['1.00', '50.00', '50.00'],
            ['2.00', '0.00', '0.00'],
        ])
        ->and($specification->subtotal)->toBe('50.00')
        ->and($specification->total_hours)->toBe('3.00')
        ->and($specification->specification_text)->toContain('(no rate)');
});

it('only takes unbilled billable entries of the client inside the period', function (): void {
    $project = Project::factory()->for($this->client)->create();
    $otherProject = Project::factory()->create();

    $inside = TimeEntry::factory()->for($this->user)->for($project)->create(['spent_on' => '2026-08-15']);
    TimeEntry::factory()->for($this->user)->for($project)->create(['spent_on' => '2026-07-31']);
    TimeEntry::factory()->for($this->user)->for($project)->create(['spent_on' => '2026-09-01']);
    TimeEntry::factory()->for($this->user)->for($project)->billed()->create(['spent_on' => '2026-08-15']);
    TimeEntry::factory()->for($this->user)->for($project)->create(['spent_on' => '2026-08-15', 'is_billable' => false]);
    TimeEntry::factory()->for($this->user)->for($project)->running()->create(['spent_on' => '2026-08-15']);
    TimeEntry::factory()->for($this->user)->for($otherProject)->create(['spent_on' => '2026-08-15']);

    $specification = app(BuildInvoiceSpecificationAction::class)->handle($this->client, $this->from, $this->to);

    expect($specification->entries->pluck('id')->all())->toBe([$inside->id]);
});

it('can be restricted to a subset of entries', function (): void {
    $project = Project::factory()->for($this->client)->create();
    $keep = TimeEntry::factory()->for($this->user)->for($project)->create(['spent_on' => '2026-08-15', 'hours' => '1.00']);
    TimeEntry::factory()->for($this->user)->for($project)->create(['spent_on' => '2026-08-16', 'hours' => '2.00']);

    $specification = app(BuildInvoiceSpecificationAction::class)->handle($this->client, $this->from, $this->to, [(string) $keep->id]);

    expect($specification->entries->pluck('id')->all())->toBe([$keep->id])
        ->and($specification->total_hours)->toBe('1.00');
});

it('returns an empty specification when there is nothing to invoice', function (): void {
    $specification = app(BuildInvoiceSpecificationAction::class)->handle($this->client, $this->from, $this->to);

    expect($specification->isEmpty())->toBeTrue()
        ->and($specification->lines)->toBeEmpty()
        ->and($specification->subtotal)->toBe('0.00');
});
