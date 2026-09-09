<?php

declare(strict_types=1);

use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\Harvest\Jobs\ImportFromHarvestJob;
use App\Domain\Harvest\Models\HarvestImport;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\HarvestApi;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('renders the integrations page without any configuration', function (): void {
    HarvestApi::unconfigure();
    config(['services.moneybird.access_token' => null, 'services.moneybird.administration_id' => null]);
    Http::fake();

    $this->get(route('integrations.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Integrations')
            ->where('harvest.configured', false)
            ->where('harvest.account_name', null)
            ->where('harvest.last_import', null)
            ->where('harvest.imports', [])
            ->where('harvest.progress', null)
            ->where('moneybird.configured', false));

    Http::assertNothingSent();
});

it('renders the connected account and import history when configured', function (): void {
    HarvestApi::configure();
    config(['services.moneybird.access_token' => 'mb-token', 'services.moneybird.administration_id' => '999']);
    HarvestApi::fake();
    HarvestImport::factory()->count(12)->create();
    $latest = HarvestImport::factory()->failed('Harvest API GET /users failed with status 401.')->create(['started_at' => now()->subMinute()]);

    $this->get(route('integrations.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Integrations')
            ->where('harvest.configured', true)
            ->where('harvest.account_name', 'Sietze Keuning')
            ->where('harvest.account_email', 'sietze@example.test')
            ->where('harvest.account_error', null)
            ->where('harvest.last_import.id', $latest->id)
            ->where('harvest.last_import.status', HarvestImportStatus::Failed->value)
            ->where('harvest.last_import.error', 'Harvest API GET /users failed with status 401.')
            ->has('harvest.imports', 10)
            ->where('harvest.imports.1.counts.clients.created', 2)
            ->where('moneybird.configured', true));
});

it('shows the account error instead of failing the page', function (): void {
    HarvestApi::configure();
    HarvestApi::fake([
        HarvestApi::BASE.'/users/me*' => Http::response(['message' => 'Invalid token'], 401),
    ]);

    $this->get(route('integrations.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('harvest.configured', true)
            ->where('harvest.account_name', null)
            ->where('harvest.account_error', fn (string $error) => str_contains($error, 'status 401')));
});

it('queues an import from the settings page and reports progress', function (): void {
    HarvestApi::configure();
    Queue::fake();

    $this->postJson(route('integrations.harvest.import'))
        ->assertOk()
        ->assertJsonPath('progress.status', HarvestImportStatus::Queued->value);

    Queue::assertPushed(ImportFromHarvestJob::class, 1);

    $this->getJson(route('integrations.harvest.progress'))
        ->assertOk()
        ->assertJsonPath('progress.status', HarvestImportStatus::Queued->value)
        ->assertJsonPath('progress.counts.users.created', 0);

    // A second click while queued does not start another job.
    $this->postJson(route('integrations.harvest.import'))
        ->assertOk()
        ->assertJsonPath('progress.status', HarvestImportStatus::Queued->value);
    Queue::assertPushed(ImportFromHarvestJob::class, 1);
});

it('returns null progress when nothing has run and refuses without configuration', function (): void {
    HarvestApi::unconfigure();
    Queue::fake();

    $this->getJson(route('integrations.harvest.progress'))->assertOk()->assertExactJson(['progress' => null]);

    $this->postJson(route('integrations.harvest.import'))
        ->assertOk()
        ->assertJsonPath('progress.status', HarvestImportStatus::Failed->value)
        ->assertJsonPath('progress.error', fn (string $error) => str_contains($error, 'HARVEST_ACCOUNT_ID'));

    Queue::assertNothingPushed();
    expect(HarvestImportProgressData::load()?->status)->toBe(HarvestImportStatus::Failed);
});

it('requires authentication for the harvest endpoints', function (): void {
    auth()->logout();

    $this->get(route('integrations.edit'))->assertRedirect(route('login'));
    $this->postJson(route('integrations.harvest.import'))->assertUnauthorized();
    $this->getJson(route('integrations.harvest.progress'))->assertUnauthorized();
});
