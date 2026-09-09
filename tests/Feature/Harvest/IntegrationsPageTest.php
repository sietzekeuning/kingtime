<?php

declare(strict_types=1);

use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\Harvest\Jobs\ImportFromHarvestJob;
use App\Domain\Harvest\Models\HarvestConnection;
use App\Domain\Harvest\Models\HarvestImport;
use App\Domain\Moneybird\Models\MoneybirdConnection;
use App\Domain\User\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\HarvestApi;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the integrations page for a user without connections', function (): void {
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
            ->where('moneybird.configured', false)
            ->where('moneybird.administration_id', null));

    Http::assertNothingSent();
});

it('renders the connected accounts and the import history of the user only', function (): void {
    HarvestApi::connect($this->user);
    MoneybirdConnection::factory()->for($this->user)->create(['administration_id' => '123456789', 'tax_rate_id' => '433000000000000001']);
    HarvestApi::fake([
        'moneybird.com/api/v2/administrations.json' => Http::response(file_get_contents(base_path('tests/Fixtures/moneybird/administrations.json'))),
    ]);
    HarvestImport::factory()->count(12)->for($this->user)->create();
    HarvestImport::factory()->count(3)->create(['started_at' => now()]);
    $latest = HarvestImport::factory()->for($this->user)->failed('Harvest API GET /users failed with status 401.')->create(['started_at' => now()->subMinute()]);

    $this->get(route('integrations.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Integrations')
            ->where('harvest.configured', true)
            ->where('harvest.account_id', '12345')
            ->where('harvest.account_name', 'Sietze Keuning')
            ->where('harvest.account_email', 'sietze@example.test')
            ->where('harvest.account_error', null)
            ->where('harvest.last_import.id', $latest->id)
            ->where('harvest.last_import.status', HarvestImportStatus::Failed->value)
            ->where('harvest.last_import.error', 'Harvest API GET /users failed with status 401.')
            ->has('harvest.imports', 10)
            ->where('harvest.imports.1.counts.clients.created', 2)
            ->where('moneybird.configured', true)
            ->where('moneybird.administration_id', '123456789')
            ->where('moneybird.administration_name', 'King Websites')
            ->where('moneybird.administration_error', null)
            ->where('moneybird.tax_rate_id', '433000000000000001'));
});

it('shows the account errors instead of failing the page', function (): void {
    HarvestApi::connect($this->user);
    MoneybirdConnection::factory()->for($this->user)->create(['administration_id' => '42']);
    HarvestApi::fake([
        HarvestApi::BASE.'/users/me*' => Http::response(['message' => 'Invalid token'], 401),
        'moneybird.com/api/v2/administrations.json' => Http::response(file_get_contents(base_path('tests/Fixtures/moneybird/administrations.json'))),
    ]);

    $this->get(route('integrations.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('harvest.configured', true)
            ->where('harvest.account_name', null)
            ->where('harvest.account_error', fn (string $error) => str_contains($error, 'status 401'))
            ->where('moneybird.configured', true)
            ->where('moneybird.administration_error', fn (string $error) => str_contains($error, 'cannot see administration 42')));
});

it('connects harvest after checking the token and links the harvest user', function (): void {
    HarvestApi::fake();

    $this->from(route('integrations.edit'))
        ->post(route('integrations.harvest.store'), ['account_id' => '12345', 'access_token' => 'test-token'])
        ->assertRedirect(route('integrations.edit'))
        ->assertSessionHas('toast.message', 'Harvest connected as Sietze Keuning.');

    $connection = HarvestConnection::query()->where('user_id', $this->user->id)->sole();
    expect($connection->account_id)->toBe('12345')
        ->and($connection->access_token)->toBe('test-token')
        ->and($connection->harvest_user_id)->toBe(1001)
        ->and($connection->account_email)->toBe('sietze@example.test')
        ->and($connection->getRawOriginal('access_token'))->not->toContain('test-token');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/users/me')
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->hasHeader('Harvest-Account-Id', '12345'));
});

it('rejects harvest credentials the api does not accept', function (): void {
    HarvestApi::fake([
        HarvestApi::BASE.'/users/me*' => Http::response(['message' => 'Invalid token'], 401),
    ]);

    $this->from(route('integrations.edit'))
        ->post(route('integrations.harvest.store'), ['account_id' => '12345', 'access_token' => 'wrong'])
        ->assertRedirect(route('integrations.edit'))
        ->assertInvalid(['access_token' => 'status 401']);

    $this->post(route('integrations.harvest.store'), ['account_id' => 'abc', 'access_token' => ''])
        ->assertSessionHasErrors(['account_id', 'access_token']);

    expect(HarvestConnection::query()->count())->toBe(0);
});

it('disconnects harvest and keeps the imported data', function (): void {
    HarvestApi::connect($this->user);
    HarvestImport::factory()->for($this->user)->create();
    HarvestImportProgressData::queued()->store($this->user->id);

    $this->delete(route('integrations.harvest.destroy'))
        ->assertSessionHas('toast.message', fn (string $message) => str_contains($message, 'disconnected'));

    expect(HarvestConnection::query()->count())->toBe(0)
        ->and(HarvestImport::query()->count())->toBe(1)
        ->and(HarvestImportProgressData::load($this->user->id))->toBeNull();
});

it('connects moneybird after checking that the token sees the administration', function (): void {
    Http::fake(['moneybird.com/api/v2/administrations.json' => Http::response(file_get_contents(base_path('tests/Fixtures/moneybird/administrations.json')))]);

    $this->from(route('integrations.edit'))
        ->post(route('integrations.moneybird.store'), [
            'access_token' => 'secret-token',
            'administration_id' => '123456789',
            'tax_rate_id' => '433000000000000001',
            'ledger_account_id' => '',
            'workflow_id' => null,
        ])
        ->assertRedirect(route('integrations.edit'))
        ->assertSessionHas('toast.message', 'Moneybird connected to King Websites.');

    $connection = MoneybirdConnection::query()->where('user_id', $this->user->id)->sole();
    expect($connection->access_token)->toBe('secret-token')
        ->and($connection->administration_name)->toBe('King Websites')
        ->and($connection->tax_rate_id)->toBe('433000000000000001')
        ->and($connection->ledger_account_id)->toBeNull()
        ->and($connection->getRawOriginal('access_token'))->not->toContain('secret-token');

    // Changing the settings without retyping the token keeps it.
    $this->post(route('integrations.moneybird.store'), ['access_token' => '', 'administration_id' => '123456789', 'workflow_id' => '435000000000000001'])
        ->assertSessionHasNoErrors();

    expect($connection->fresh()?->access_token)->toBe('secret-token')
        ->and($connection->fresh()?->workflow_id)->toBe('435000000000000001')
        ->and($connection->fresh()?->tax_rate_id)->toBeNull();
});

it('rejects a moneybird token that cannot see the administration', function (): void {
    Http::fake(['moneybird.com/api/v2/administrations.json' => Http::sequence()
        ->push(file_get_contents(base_path('tests/Fixtures/moneybird/administrations.json')))
        ->push(['error' => 'unauthorized'], 401)]);

    $this->from(route('integrations.edit'))
        ->post(route('integrations.moneybird.store'), ['access_token' => 'secret-token', 'administration_id' => '42'])
        ->assertInvalid(['administration_id' => 'cannot see administration 42']);

    $this->post(route('integrations.moneybird.store'), ['access_token' => '', 'administration_id' => '42'])
        ->assertSessionHasErrors(['access_token' => 'Enter a Moneybird API token.']);

    $this->post(route('integrations.moneybird.store'), ['access_token' => 'wrong', 'administration_id' => '42'])
        ->assertInvalid(['access_token' => 'HTTP 401']);

    expect(MoneybirdConnection::query()->count())->toBe(0);
});

it('disconnects moneybird', function (): void {
    MoneybirdConnection::factory()->for($this->user)->create();

    $this->delete(route('integrations.moneybird.destroy'))
        ->assertSessionHas('toast.message', 'Moneybird disconnected.');

    expect(MoneybirdConnection::query()->count())->toBe(0);
});

it('queues an import with the user\'s own connection and reports progress', function (): void {
    $connection = HarvestApi::connect($this->user);
    Queue::fake();

    $this->postJson(route('integrations.harvest.import'))
        ->assertOk()
        ->assertJsonPath('progress.status', HarvestImportStatus::Queued->value);

    Queue::assertPushed(ImportFromHarvestJob::class, fn (ImportFromHarvestJob $job) => $job->harvestConnection->is($connection));

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

it('returns null progress when nothing has run and refuses without a connection', function (): void {
    Queue::fake();

    $this->getJson(route('integrations.harvest.progress'))->assertOk()->assertExactJson(['progress' => null]);

    $this->postJson(route('integrations.harvest.import'))
        ->assertOk()
        ->assertJsonPath('progress.status', HarvestImportStatus::Failed->value)
        ->assertJsonPath('progress.error', fn (string $error) => str_contains($error, 'not connected'));

    Queue::assertNothingPushed();
    expect(HarvestImportProgressData::load($this->user->id)?->status)->toBe(HarvestImportStatus::Failed);
});

it('requires authentication for the integration endpoints', function (): void {
    auth()->logout();

    $this->get(route('integrations.edit'))->assertRedirect(route('login'));
    $this->post(route('integrations.harvest.store'))->assertRedirect(route('login'));
    $this->post(route('integrations.moneybird.store'))->assertRedirect(route('login'));
    $this->postJson(route('integrations.harvest.import'))->assertUnauthorized();
    $this->getJson(route('integrations.harvest.progress'))->assertUnauthorized();
});
