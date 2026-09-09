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
    $this->owner = User::factory()->create(['email' => 'sietze@example.test', 'name' => 'Local Sietze']);
    $this->connection = HarvestApi::connect($this->owner);
});

it('imports everything from harvest with the right mappings', function (): void {
    HarvestApi::fake();
    $existing = $this->owner;

    $result = app(ImportFromHarvestAction::class)->handle($this->connection);

    expect($result->clients->created)->toBe(2)
        ->and($result->projects->created)->toBe(3)
        ->and($result->time_entries->created)->toBe(2);

    // The token owner becomes the Harvest user behind the token; nobody else gets an account.
    expect($existing->fresh()->harvest_id)->toBe(1001)
        ->and($existing->fresh()->name)->toBe('Local Sietze')
        ->and($this->connection->fresh()->harvest_user_id)->toBe(1001)
        ->and($this->connection->fresh()->account_email)->toBe('sietze@example.test')
        ->and(User::query()->count())->toBe(1);

    // Clients come from two pages and belong to the owner.
    $acme = Client::query()->where('harvest_id', 2001)->firstOrFail();
    $globex = Client::query()->where('harvest_id', 2002)->firstOrFail();
    expect($acme->user_id)->toBe($existing->id)
        ->and($globex->user_id)->toBe($existing->id)
        ->and($acme->name)->toBe('Acme')
        ->and($acme->address)->toBe("1 Main Street\nAmsterdam")
        ->and($globex->is_active)->toBeFalse()
        ->and($globex->currency)->toBe('USD');

    // Projects map billability, rates, budget and dates.
    $website = Project::query()->where('harvest_id', 3001)->firstOrFail();
    expect($website->client_id)->toBe($acme->id)
        ->and($website->user_id)->toBe($existing->id)
        ->and($website->code)->toBe('WEB')
        ->and($website->is_billable)->toBeTrue()
        ->and($website->hourly_rate)->toBe('95.00')
        ->and($website->budget_hours)->toBe('100.00')
        ->and($website->budget_amount)->toBeNull()
        ->and($website->starts_on?->toDateString())->toBe('2026-01-01')
        ->and($website->ends_on)->toBeNull()
        ->and($website->notes)->toBe('Relaunch of the marketing site');

    // A project Harvest billed per task keeps no rate of its own.
    $support = Project::query()->where('harvest_id', 3002)->firstOrFail();
    expect($support->client_id)->toBe($globex->id)
        ->and($support->is_billable)->toBeTrue()
        ->and($support->hourly_rate)->toBeNull()
        ->and($support->budget_hours)->toBeNull()
        ->and($support->budget_amount)->toBe('5000.00')
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

    // Another Harvest user's entry is not imported; Harvest is asked for the owner's entries only.
    expect(TimeEntry::query()->where('harvest_id', 6002)->exists())->toBeFalse();
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/time_entries')
        && (HarvestApi::query($request)['user_id'] ?? null) === '1001');

    // Tasks are not imported; an entry without notes keeps the task name.
    $running = TimeEntry::query()->where('harvest_id', 6003)->firstOrFail();
    expect($running->is_running)->toBeTrue()
        ->and($running->hours)->toBe('0.50')
        ->and($running->notes)->toBe('Development')
        ->and($running->timer_started_at?->toIso8601String())->toBe('2026-09-09T08:00:00+00:00');
});

it('follows next_page pagination and sends the harvest headers', function (): void {
    HarvestApi::fake();

    app(ImportFromHarvestAction::class)->handle($this->connection);

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

    $importer->handle($this->connection);

    $acme = Client::query()->where('harvest_id', 2001)->firstOrFail();
    $acme->update(['email' => 'billing@acme.test', 'moneybird_contact_id' => 'mb-1', 'notes' => 'Local note']);
    $website = Project::query()->where('harvest_id', 3001)->firstOrFail();
    $website->update(['color' => '#ff6600', 'name' => 'Renamed locally']);

    $result = $importer->handle($this->connection);

    expect($result->clients)->toMatchObject(['created' => 0, 'updated' => 2])
        ->and($result->projects)->toMatchObject(['created' => 0, 'updated' => 3])
        ->and($result->time_entries)->toMatchObject(['created' => 0, 'updated' => 2]);

    expect(Client::query()->count())->toBe(2)
        ->and(Project::query()->count())->toBe(3)
        ->and(TimeEntry::query()->count())->toBe(2)
        ->and(User::query()->count())->toBe(1);

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
    $importer->handle($this->connection);

    $entry = TimeEntry::query()->where('harvest_id', 6001)->firstOrFail();
    $entry->update(['is_billed' => true, 'invoice_id' => null]);
    $importer->handle($this->connection);
    expect($entry->fresh()->is_billed)->toBeFalse();

    $invoice = Invoice::factory()->create();
    $entry->refresh()->update(['is_billed' => true, 'invoice_id' => $invoice->id]);
    $importer->handle($this->connection);
    expect($entry->fresh()->is_billed)->toBeTrue();
});

it('passes updated_since to every list endpoint on an incremental import', function (): void {
    HarvestApi::fake();
    $since = now()->subDay();

    app(ImportFromHarvestAction::class)->handle($this->connection, $since);

    foreach (['/clients', '/projects', '/time_entries'] as $path) {
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

    $me = (new HarvestClient($this->connection))->me();

    expect($me['email'])->toBe('sietze@example.test');
    Sleep::assertSleptTimes(1);
    Sleep::assertSequence([Sleep::for(3)->seconds()]);
});

it('throws a helpful exception on an error response', function (): void {
    HarvestApi::fake([
        HarvestApi::BASE.'/users/me*' => Http::response(['message' => 'Invalid token'], 401),
    ]);

    expect(fn () => (new HarvestClient($this->connection))->me())
        ->toThrow(HarvestException::class, 'GET /users/me failed with status 401: Invalid token. The Harvest access token is invalid or revoked');
});

it('has no client for a user without a harvest connection', function (): void {
    HarvestApi::fake();
    $other = User::factory()->create();

    expect(HarvestClient::forUser($other))->toBeNull()
        ->and(HarvestClient::forUser($this->owner))->toBeInstanceOf(HarvestClient::class)
        ->and(fn () => HarvestClient::forUserOrFail($other))
        ->toThrow(HarvestException::class, 'not connected');

    Http::assertNothingSent();
});

it('does not steal a harvest id that another local user already carries', function (): void {
    HarvestApi::fake();
    $alreadyLinked = User::factory()->create(['email' => 'other@example.test', 'harvest_id' => 1001]);

    app(ImportFromHarvestAction::class)->handle($this->connection);

    expect($this->owner->fresh()->harvest_id)->toBeNull()
        ->and($alreadyLinked->fresh()->harvest_id)->toBe(1001)
        ->and($this->connection->fresh()->harvest_user_id)->toBe(1001);

    // The hours still land on the owner: the connection knows whose token it is.
    expect(TimeEntry::query()->where('harvest_id', 6001)->firstOrFail()->user_id)->toBe($this->owner->id)
        ->and(TimeEntry::ownedBy($alreadyLinked)->count())->toBe(0);
});

it('keeps two users\' harvest imports apart', function (): void {
    HarvestApi::fake();
    $other = User::factory()->create(['email' => 'other@example.test']);
    $otherConnection = HarvestApi::connect($other);

    app(ImportFromHarvestAction::class)->handle($this->connection);
    app(ImportFromHarvestAction::class)->handle($otherConnection);

    expect(Client::ownedBy($this->owner)->count())->toBe(2)
        ->and(Client::ownedBy($other)->count())->toBe(2)
        ->and(Project::ownedBy($other)->count())->toBe(3)
        ->and(TimeEntry::ownedBy($other)->count())->toBe(2)
        ->and(Project::ownedBy($other)->where('harvest_id', 3001)->firstOrFail()->client->user_id)->toBe($other->id);
});
