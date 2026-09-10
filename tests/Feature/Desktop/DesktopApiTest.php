<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

/**
 * The JSON API behind the menu bar app: sign in with the Kingtime
 * credentials, pick a project, press play, and take idle time off again.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-10 10:00:00'));
    $this->user = User::factory()->create(['email' => 'sietze@example.com']);
    $this->client = Client::factory()->for($this->user)->create(['name' => 'Acme']);
    $this->project = Project::factory()->for($this->client)->create(['name' => 'Website', 'is_billable' => true, 'hourly_rate' => '95.00']);
});

function desktopSignIn(array $overrides = []): TestResponse
{
    return test()->postJson(route('desktop.tokens.store'), [
        'email' => 'sietze@example.com',
        'password' => 'password',
        'device_name' => 'MacBook Pro',
        ...$overrides,
    ]);
}

it('signs in with email and password and hands out a token named after the device', function (): void {
    desktopSignIn()
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->whereType('token', 'string')
            ->where('user.id', $this->user->id)
            ->where('user.name', $this->user->name)
            ->where('user.email', 'sietze@example.com'));

    expect($this->user->tokens()->count())->toBe(1)
        ->and($this->user->tokens()->first()?->name)->toBe('Kingtime for Mac (MacBook Pro)');
});

it('rejects a wrong password without leaking whether the account exists', function (): void {
    desktopSignIn(['password' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('email');
    desktopSignIn(['email' => 'nobody@example.com'])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect($this->user->tokens()->count())->toBe(0);
});

it('signs in an account whose email is not verified yet', function (): void {
    $this->user->forceFill(['email_verified_at' => null])->save();

    desktopSignIn()->assertCreated();
});

it('asks for the two-factor code and accepts a valid one or a recovery code', function (): void {
    $secret = app(Google2FA::class)->generateSecretKey();
    $this->user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['rescue-me', 'spare-one'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    desktopSignIn()->assertUnprocessable()->assertJsonValidationErrors('code');
    desktopSignIn(['code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');

    desktopSignIn(['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertCreated();
    desktopSignIn(['code' => 'rescue-me'])->assertCreated();

    expect($this->user->fresh()?->recoveryCodes())->not->toContain('rescue-me')->toContain('spare-one')
        ->and($this->user->tokens()->count())->toBe(2);
});

it('requires a token for everything else', function (): void {
    $this->getJson(route('desktop.state'))->assertUnauthorized();
    $this->postJson(route('desktop.timer.store'), ['project_id' => $this->project->id])->assertUnauthorized();
});

it('describes the signed-in user, the active projects and the running timer', function (): void {
    Project::factory()->for($this->client)->create(['name' => 'Archived', 'is_active' => false]);
    Project::factory()->create(['name' => 'Someone else\'s']);
    $running = TimeEntry::factory()->for($this->user)->for($this->project)
        ->create(['hours' => '1.00', 'is_running' => true, 'timer_started_at' => Carbon::parse('2026-09-10 09:30:00'), 'notes' => 'Homepage']);

    Sanctum::actingAs($this->user);

    $this->getJson(route('desktop.state'))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('user.email', 'sietze@example.com')
            ->has('projects', 1)
            ->where('projects.0.id', $this->project->id)
            ->where('projects.0.name', 'Website')
            ->where('projects.0.client_name', 'Acme')
            ->where('timer.id', $running->id)
            ->where('timer.seconds_before_timer', 3600)
            ->where('timer.timer_started_at', '2026-09-10T09:30:00+00:00')
            ->where('timer.notes', 'Homepage')
            ->where('timer.project_name', 'Website')
            ->where('timer.client_name', 'Acme')
            ->where('server_time', now()->toIso8601String()));
});

it('starts a timer on a project for today and resumes the same entry after a stop', function (): void {
    Sanctum::actingAs($this->user);

    $this->postJson(route('desktop.timer.store'), ['project_id' => $this->project->id, 'notes' => ' Homepage '])
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('timer.project_id', $this->project->id)
            ->where('timer.spent_on', '2026-09-10')
            ->where('timer.seconds_before_timer', 0)
            ->where('timer.timer_started_at', now()->toIso8601String())
            ->where('timer.notes', 'Homepage')
            ->etc());

    $this->travelTo(Carbon::parse('2026-09-10 10:30:00'));

    $this->deleteJson(route('desktop.timer.destroy'))
        ->assertOk()
        ->assertJson(['timer' => null]);

    $entry = $this->user->timeEntries()->sole();
    expect($entry->hours)->toBe('0.50')->and($entry->is_running)->toBeFalse();

    $this->postJson(route('desktop.timer.store'), ['project_id' => $this->project->id, 'notes' => 'Homepage'])
        ->assertCreated()
        ->assertJson(['timer' => ['id' => $entry->id, 'seconds_before_timer' => 1800]]);

    $this->postJson(route('desktop.timer.store'), ['project_id' => $this->project->id, 'notes' => 'Footer'])
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json->where('timer.notes', 'Footer')->where('timer.seconds_before_timer', 0)->etc());

    expect($this->user->timeEntries()->count())->toBe(2)
        ->and($this->user->timeEntries()->where('is_running', true)->count())->toBe(1)
        ->and($entry->fresh()?->is_running)->toBeFalse();
});

it('refuses to start a timer on another user\'s project', function (): void {
    $foreign = Project::factory()->create();

    Sanctum::actingAs($this->user);

    $this->postJson(route('desktop.timer.store'), ['project_id' => $foreign->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('project_id');
});

it('deducts idle time from the running timer, spilling into the stored hours when needed', function (): void {
    $entry = TimeEntry::factory()->for($this->user)->for($this->project)
        ->create(['hours' => '1.00', 'is_running' => true, 'timer_started_at' => Carbon::parse('2026-09-10 09:30:00')]);

    Sanctum::actingAs($this->user);

    $this->postJson(route('desktop.timer.idle'), ['time_entry_id' => $entry->id, 'seconds' => 20 * 60])
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('timer.seconds_before_timer', 3600)
            ->where('timer.timer_started_at', '2026-09-10T09:50:00+00:00')
            ->etc());

    $this->postJson(route('desktop.timer.idle'), ['time_entry_id' => $entry->id, 'seconds' => 40 * 60, 'stop' => true])
        ->assertCreated()
        ->assertJson(['timer' => null]);

    $entry->refresh();
    expect($entry->is_running)->toBeFalse()
        ->and($entry->timer_started_at)->toBeNull()
        ->and($entry->hours)->toBe('0.50');
});

it('never deducts from another user\'s entry', function (): void {
    $foreign = TimeEntry::factory()->running()->create();

    Sanctum::actingAs($this->user);

    $this->postJson(route('desktop.timer.idle'), ['time_entry_id' => $foreign->id, 'seconds' => 600])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('time_entry_id');

    expect($foreign->fresh()?->is_running)->toBeTrue();
});

it('signs out by revoking the token it was called with', function (): void {
    $token = desktopSignIn()->json('token');

    $this->withToken($token)->deleteJson(route('desktop.tokens.destroy'))->assertNoContent();

    expect($this->user->tokens()->count())->toBe(0);

    // The guard remembers the user for the rest of a request; a new request
    // has to resolve the (now deleted) token again.
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson(route('desktop.state'))->assertUnauthorized();
});
