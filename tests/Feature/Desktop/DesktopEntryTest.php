<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;

/**
 * The day view of the menu bar app: entries are added, changed and deleted
 * one by one, and the play button on an entry continues its hours.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-10 10:00:00'));
    $this->user = User::factory()->create();
    $this->client = Client::factory()->for($this->user)->create(['name' => 'Acme']);
    $this->project = Project::factory()->for($this->client)->create(['name' => 'Website', 'hourly_rate' => '95.00', 'is_billable' => true]);
});

it('requires a token', function (): void {
    $entry = TimeEntry::factory()->for($this->user)->for($this->project)->create();

    $this->postJson(route('desktop.entries.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-10', 'hours' => '0.50'])->assertUnauthorized();
    $this->patchJson(route('desktop.entries.update', $entry), ['hours' => '1.00'])->assertUnauthorized();
    $this->deleteJson(route('desktop.entries.destroy', $entry))->assertUnauthorized();
    $this->postJson(route('desktop.entries.timer', $entry))->assertUnauthorized();
});

it('adds an entry and answers with the week, the day\'s entries included', function (): void {
    Sanctum::actingAs($this->user);

    $this->postJson(route('desktop.entries.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-08', 'hours' => '0.50', 'notes' => 'Standup'])
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('selected_date', '2026-09-08')
            ->where('day_total', '0.50')
            ->has('entries', 1)
            ->where('entries.0.project_name', 'Website')
            ->where('entries.0.client_name', 'Acme')
            ->where('entries.0.hours', '0.50')
            ->where('entries.0.notes', 'Standup')
            ->etc());

    expect(TimeEntry::query()->sole())
        ->user_id->toBe($this->user->id)
        ->hourly_rate->toBe('95.00');
});

it('changes the hours and notes of an entry, but not while its timer runs or once it is billed', function (): void {
    $entry = TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-10', 'hours' => '0.50', 'notes' => 'Old']);

    Sanctum::actingAs($this->user);

    $this->patchJson(route('desktop.entries.update', $entry), ['hours' => '0.75', 'notes' => 'New'])
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->where('day_total', '0.75')->where('entries.0.notes', 'New')->etc());

    expect($entry->refresh())->hours->toBe('0.75')->spent_on->toDateString()->toBe('2026-09-10');

    $entry->update(['is_running' => true, 'timer_started_at' => now()]);
    $this->patchJson(route('desktop.entries.update', $entry), ['hours' => '2.00'])->assertJsonValidationErrors('hours');

    $entry->update(['is_running' => false, 'timer_started_at' => null, 'is_billed' => true]);
    $this->patchJson(route('desktop.entries.update', $entry), ['hours' => '2.00'])->assertJsonValidationErrors('hours');
    $this->deleteJson(route('desktop.entries.destroy', $entry))->assertJsonValidationErrors('hours');

    expect($entry->refresh()->hours)->toBe('0.75');
});

it('deletes an entry', function (): void {
    $entry = TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-10', 'hours' => '1.00']);

    Sanctum::actingAs($this->user);

    $this->deleteJson(route('desktop.entries.destroy', $entry))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->where('day_total', '0.00')->has('entries', 0)->etc());

    expect(TimeEntry::query()->count())->toBe(0);
});

it('continues the timer on an entry on top of its hours and stops the one that ran', function (): void {
    $running = TimeEntry::factory()->for($this->user)->for($this->project)->running()->create(['spent_on' => '2026-09-10', 'hours' => '0.00', 'timer_started_at' => now()->subMinutes(15)]);
    $entry = TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-10', 'hours' => '0.50', 'notes' => 'Review']);

    Sanctum::actingAs($this->user);

    $this->postJson(route('desktop.entries.timer', $entry))
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('timer.id', $entry->id)
            ->where('timer.seconds_before_timer', 1800)
            ->etc());

    expect($running->refresh())->is_running->toBeFalse()->hours->toBe('0.25')
        ->and($entry->refresh()->is_running)->toBeTrue();
});

it('never touches another user\'s entry', function (): void {
    $foreign = TimeEntry::factory()->create(['hours' => '1.00']);

    Sanctum::actingAs($this->user);

    $this->patchJson(route('desktop.entries.update', $foreign->id), ['hours' => '5.00'])->assertNotFound();
    $this->deleteJson(route('desktop.entries.destroy', $foreign->id))->assertNotFound();
    $this->postJson(route('desktop.entries.timer', $foreign->id))->assertNotFound();
    $this->postJson(route('desktop.entries.store'), ['project_id' => $foreign->project_id, 'spent_on' => '2026-09-10', 'hours' => '1.00'])
        ->assertJsonValidationErrors('project_id');

    expect($foreign->withoutGlobalScopes()->find($foreign->id))->hours->toBe('1.00')->is_running->toBeFalse();
});
