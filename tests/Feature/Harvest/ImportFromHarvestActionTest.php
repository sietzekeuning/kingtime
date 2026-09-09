<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Harvest\Actions\ImportFromHarvestAction;
use App\Domain\Harvest\Exceptions\HarvestException;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\HarvestApi;

beforeEach(function (): void {
    HarvestApi::configure();
});

it('imports everything from harvest with the right mappings', function (): void {
    HarvestApi::fake();
    $existing = User::factory()->create(['email' => 'sietze@example.test', 'name' => 'Local Sietze']);

    $result = app(ImportFromHarvestAction::class)->handle();

    expect($result->users->created)->toBe(1)
        ->and($result->users->updated)->toBe(1)
        ->and($result->clients->created)->toBe(2)
        ->and($result->projects->created)->toBe(3)
        ->and($result->time_entries->created)->toBe(3);

    // Users: the existing account is adopted by email, the unknown one is created.
    expect($existing->fresh()->harvest_id)->toBe(1001)
        ->and($existing->fresh()->name)->toBe('Local Sietze');
    $alex = User::query()->where('harvest_id', 1002)->firstOrFail();
    expect($alex->email)->toBe('alex@example.test')
        ->and($alex->name)->toBe('Alex Doe')
        ->and($alex->email_verified_at)->toBeNull();

    // Clients come from two pages.
    $acme = Client::query()->where('harvest_id', 2001)->firstOrFail();
    $globex = Client::query()->where('harvest_id', 2002)->firstOrFail();
    expect($acme->name)->toBe('Acme')
        ->and($acme->address)->toBe("1 Main Street\nAmsterdam")
        ->and($globex->is_active)->toBeFalse()
        ->and($globex->currency)->toBe('USD');

    // Projects map billability, rates, budget and dates.
    $website = Project::query()->where('harvest_id', 3001)->firstOrFail();
    expect($website->client_id)->toBe($acme->id)
        ->and($website->code)->toBe('WEB')
        ->and($website->is_billable)->toBeTrue()
        ->and($website->hourly_rate)->toBe('95.00')
        ->and($website->budget_hours)->toBe('100.00')
        ->and($website->starts_on?->toDateString())->toBe('2026-01-01')
        ->and($website->ends_on)->toBeNull()
        ->and($website->notes)->toBe('Relaunch of the marketing site');

    // A project Harvest billed per task keeps no rate of its own.
    $support = Project::query()->where('harvest_id', 3002)->firstOrFail();
    expect($support->client_id)->toBe($globex->id)
        ->and($support->is_billable)->toBeTrue()
        ->and($support->hourly_rate)->toBeNull()
        ->and($support->budget_hours)->toBeNull()
        ->and($support->is_active)->toBeFalse()
        ->and($support->ends_on?->toDateString())->toBe('2026-12-31');

    expect(Project::query()->where('harvest_id', 3003)->firstOrFail()->is_billable)->toBeFalse();

    // Time entries, including a billed one and a running timer.
    $entry = TimeEntry::query()->where('harvest_id', 6001)->firstOrFail();
    expect($entry->user_id)->toBe($existing->id)
        ->and($entry->project_id)->toBe($website->id)
        ->and($entry->spent_on->toDateString())->toBe('2026-09-01')
        ->and($entry->hours)->toBe('2.50')
        ->and($entry->hourly_rate)->toBe('95.00')
        ->and($entry->notes)->toBe('Homepage hero')
        ->and($entry->is_billable)->toBeTrue()
        ->and($entry->is_billed)->toBeFalse()
        ->and($entry->is_running)->toBeFalse();

    $billed = TimeEntry::query()->where('harvest_id', 6002)->firstOrFail();
    expect($billed->user_id)->toBe($alex->id)
        ->and($billed->is_billable)->toBeFalse()
        ->and($billed->is_billed)->toBeTrue()
        ->and($billed->is_locked)->toBeTrue()
        ->and($billed->hourly_rate)->toBeNull()
        ->and($billed->notes)->toBe('Weekly sync');

    // Tasks are not imported; an entry without notes keeps the task name.
    $running = TimeEntry::query()->where('harvest_id', 6003)->firstOrFail();
    expect($running->is_running)->toBeTrue()
        ->and($running->hours)->toBe('0.50')
        ->and($running->notes)->toBe('Development')
        ->and($running->timer_started_at?->toIso8601String())->toBe('2026-09-09T08:00:00+00:00');
});

it('follows next_page pagination and sends the harvest headers', function (): void {
    HarvestApi::fake();

    app(ImportFromHarvestAction::class)->handle();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/clients')
        && HarvestApi::page($request) === 1
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->hasHeader('Harvest-Account-Id', '12345')
        && $request->hasHeader('User-Agent', 'Kingtime (https://kingtime.nl)'));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/clients') && HarvestApi::page($request) === 2);
    expect(Client::query()->count())->toBe(2);
});

it('updates instead of duplicating on a second run and keeps local-only fields', function (): void {
    HarvestApi::fake();
    $importer = app(ImportFromHarvestAction::class);

    $importer->handle();

    $acme = Client::query()->where('harvest_id', 2001)->firstOrFail();
    $acme->update(['email' => 'billing@acme.test', 'moneybird_contact_id' => 'mb-1', 'notes' => 'Local note']);
    $website = Project::query()->where('harvest_id', 3001)->firstOrFail();
    $website->update(['color' => '#ff6600', 'name' => 'Renamed locally']);

    $result = $importer->handle();

    expect($result->clients)->toMatchObject(['created' => 0, 'updated' => 2])
        ->and($result->projects)->toMatchObject(['created' => 0, 'updated' => 3])
        ->and($result->time_entries)->toMatchObject(['created' => 0, 'updated' => 3])
        ->and($result->users)->toMatchObject(['created' => 0, 'updated' => 2]);

    expect(Client::query()->count())->toBe(2)
        ->and(Project::query()->count())->toBe(3)
        ->and(TimeEntry::query()->count())->toBe(3)
        ->and(User::query()->count())->toBe(2);

    $acme->refresh();
    expect($acme->email)->toBe('billing@acme.test')
        ->and($acme->moneybird_contact_id)->toBe('mb-1')
        ->and($acme->notes)->toBe('Local note');

    $website->refresh();
    expect($website->color)->toBe('#ff6600')
        ->and($website->name)->toBe('Website relaunch');
});

it('keeps the billed flag of entries that are on a local invoice', function (): void {
    HarvestApi::fake();
    $importer = app(ImportFromHarvestAction::class);
    $importer->handle();

    $entry = TimeEntry::query()->where('harvest_id', 6001)->firstOrFail();
    $entry->update(['is_billed' => true, 'invoice_id' => null]);
    $importer->handle();
    expect($entry->fresh()->is_billed)->toBeFalse();

    $invoice = Invoice::factory()->create();
    $entry->refresh()->update(['is_billed' => true, 'invoice_id' => $invoice->id]);
    $importer->handle();
    expect($entry->fresh()->is_billed)->toBeTrue();
});

it('passes updated_since to every list endpoint on an incremental import', function (): void {
    HarvestApi::fake();
    $since = now()->subDay();

    app(ImportFromHarvestAction::class)->handle($since);

    foreach (['/users', '/clients', '/projects', '/time_entries'] as $path) {
        Http::assertSent(fn (Request $request) => str_contains($request->url(), $path.'?')
            && (HarvestApi::query($request)['updated_since'] ?? null) === $since->toIso8601String());
    }

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/tasks') || str_contains($request->url(), '/task_assignments'));
});

it('retries after a 429 and honours retry-after', function (): void {
    Sleep::fake();
    HarvestApi::fake([
        HarvestApi::BASE.'/users/me*' => Http::sequence()
            ->push(['message' => 'Too many requests'], 429, ['Retry-After' => '3'])
            ->push(HarvestApi::fixture('me')),
    ]);

    $me = app(HarvestClient::class)->me();

    expect($me['email'])->toBe('sietze@example.test');
    Sleep::assertSleptTimes(1);
    Sleep::assertSequence([Sleep::for(3)->seconds()]);
});

it('throws a helpful exception on an error response', function (): void {
    HarvestApi::fake([
        HarvestApi::BASE.'/users/me*' => Http::response(['message' => 'Invalid token'], 401),
    ]);

    expect(fn () => app(HarvestClient::class)->me())
        ->toThrow(HarvestException::class, 'GET /users/me failed with status 401: Invalid token. Check HARVEST_ACCESS_TOKEN.');
});

it('refuses to run when harvest is not configured', function (): void {
    HarvestApi::unconfigure();
    HarvestApi::fake();

    expect(app(HarvestClient::class)->isConfigured())->toBeFalse()
        ->and(fn () => app(ImportFromHarvestAction::class)->handle())
        ->toThrow(HarvestException::class, 'HARVEST_ACCOUNT_ID');

    Http::assertNothingSent();
});
