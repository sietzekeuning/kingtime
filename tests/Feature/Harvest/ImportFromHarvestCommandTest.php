<?php

declare(strict_types=1);

use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\Harvest\Models\HarvestImport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\HarvestApi;

beforeEach(function (): void {
    $this->connection = HarvestApi::connect();
    $this->user = $this->connection->user;
});

it('runs a full import when nothing was imported before', function (): void {
    HarvestApi::fake();

    $this->artisan('harvest:import')
        ->expectsOutputToContain('Full import finished.')
        ->assertSuccessful();

    $import = HarvestImport::query()->sole();
    expect($import->status)->toBe(HarvestImportStatus::Finished)
        ->and($import->updated_since)->toBeNull()
        ->and($import->finished_at)->not->toBeNull()
        ->and($import->counts['clients'])->toBe(['created' => 2, 'updated' => 0])
        ->and($import->counts['time_entries'])->toBe(['created' => 2, 'updated' => 0]);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/time_entries')
        && ! isset(HarvestApi::query($request)['updated_since']));
});

it('continues from the last successful import by default', function (): void {
    HarvestApi::fake();
    $previous = HarvestImport::factory()->for($this->user)->create(['started_at' => now()->subHours(3)]);
    HarvestImport::factory()->for($this->user)->failed()->create(['started_at' => now()->subHour()]);
    HarvestImport::factory()->create(['started_at' => now()->subMinutes(10)]); // another user's import does not count

    $this->artisan('harvest:import')->assertSuccessful();

    $import = HarvestImport::query()->latest('id')->firstOrFail();
    $expected = $previous->started_at->subMinutes(5);
    expect($import->updated_since?->toIso8601String())->toBe($expected->toIso8601String());

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/time_entries')
        && (HarvestApi::query($request)['updated_since'] ?? null) === $expected->toIso8601String());
});

it('accepts an explicit --since and ignores history with --full', function (): void {
    HarvestApi::fake();
    HarvestImport::factory()->for($this->user)->create(['started_at' => now()->subHours(3)]);

    $this->artisan('harvest:import', ['--since' => '2026-06-01'])->assertSuccessful();
    expect(HarvestImport::query()->latest('id')->firstOrFail()->updated_since?->toDateString())->toBe('2026-06-01');

    $this->artisan('harvest:import', ['--full' => true])->assertSuccessful();
    expect(HarvestImport::query()->latest('id')->firstOrFail()->updated_since)->toBeNull();
});

it('records a failed import and exits non-zero on an api error', function (): void {
    HarvestApi::fake([
        HarvestApi::BASE.'/clients*' => Http::response(['message' => 'Nope'], 403),
    ]);

    $this->artisan('harvest:import')
        ->expectsOutputToContain('status 403')
        ->assertFailed();

    $import = HarvestImport::query()->sole();
    expect($import->status)->toBe(HarvestImportStatus::Failed)
        ->and($import->error)->toContain('status 403')
        ->and($import->finished_at)->not->toBeNull();
});

it('fails fast when nobody has connected harvest', function (): void {
    $this->connection->delete();
    HarvestApi::fake();

    $this->artisan('harvest:import')
        ->expectsOutputToContain('not connected')
        ->assertFailed();

    expect(HarvestImport::query()->count())->toBe(0);
});

it('imports for one user with --user and refuses an unknown one', function (): void {
    HarvestApi::fake();
    $other = HarvestApi::connect();

    $this->artisan('harvest:import', ['--user' => $this->user->email])->assertSuccessful();

    expect(HarvestImport::query()->where('user_id', $this->user->id)->count())->toBe(1)
        ->and(HarvestImport::query()->where('user_id', $other->user_id)->count())->toBe(0);

    $this->artisan('harvest:import', ['--user' => 'nobody@example.test'])
        ->expectsOutputToContain('No user found')
        ->assertFailed();
});
