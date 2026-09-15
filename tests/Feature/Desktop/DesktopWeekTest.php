<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Shared\Models\Scopes\UserScope;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;

/**
 * The week grid of the menu bar app: the same Monday to Sunday timesheet as
 * the web app, walked week by week and edited cell by cell.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-10 10:00:00'));
    $this->user = User::factory()->create();
    $this->client = Client::factory()->for($this->user)->create(['name' => 'Acme']);
    $this->project = Project::factory()->for($this->client)->create(['name' => 'Website', 'hourly_rate' => '95.00', 'is_billable' => true]);
});

it('requires a token', function (): void {
    $this->getJson(route('desktop.week'))->assertUnauthorized();
    $this->postJson(route('desktop.week.cell'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-08', 'hours' => '1'])->assertUnauthorized();
    $this->deleteJson(route('desktop.week.row', ['project_id' => $this->project->id, 'week_start' => '2026-09-07']))->assertUnauthorized();
});

it('draws the week around a date with a row per project, per-day totals and a week total', function (): void {
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-07', 'hours' => '2.00']);
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-09', 'hours' => '3.50']);
    TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-14', 'hours' => '8.00']);

    Sanctum::actingAs($this->user);

    $this->getJson(route('desktop.week', ['date' => '2026-09-09']))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('week_start', '2026-09-07')
            ->where('week_end', '2026-09-13')
            ->where('week_total', '5.50')
            ->has('days', 7)
            ->where('days.0.weekday', 'Mon')
            ->where('days.0.total_hours', '2.00')
            ->where('days.3.is_today', true)
            ->where('days.6.is_weekend', true)
            ->has('rows', 1)
            ->where('rows.0.project_id', $this->project->id)
            ->where('rows.0.project_name', 'Website')
            ->where('rows.0.client_name', 'Acme')
            ->where('rows.0.total_hours', '5.50')
            ->where('rows.0.cells.2.hours', '3.50')
            ->where('rows.0.cells.1.hours', '0.00')
            ->etc());
});

it('defaults to the week around today and lists the projects of the week before', function (): void {
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-03', 'hours' => '1.00']);

    Sanctum::actingAs($this->user);

    $this->getJson(route('desktop.week'))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('week_start', '2026-09-07')
            ->where('previous_week_project_ids', [$this->project->id])
            ->etc());
});

it('fills, changes and clears a cell and answers with the week around it', function (): void {
    Sanctum::actingAs($this->user);

    $this->postJson(route('desktop.week.cell'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-08', 'hours' => '2.5'])
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json->where('week_total', '2.50')->where('rows.0.cells.1.hours', '2.50')->etc());

    $entry = TimeEntry::query()->sole();
    expect($entry->hours)->toBe('2.50')
        ->and($entry->hourly_rate)->toBe('95.00')
        ->and($entry->is_billable)->toBeTrue();

    $this->postJson(route('desktop.week.cell'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-08', 'hours' => '3'])
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json->where('week_total', '3.00')->etc());

    expect($entry->fresh()?->hours)->toBe('3.00');

    $this->postJson(route('desktop.week.cell'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-08', 'hours' => null])
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json->where('week_total', '0.00')->has('rows', 0)->etc());

    expect(TimeEntry::query()->count())->toBe(0);
});

it('refuses a cell it cannot edit and a project of someone else', function (): void {
    TimeEntry::factory()->for($this->user)->for($this->project)->billed()->create(['spent_on' => '2026-09-08', 'hours' => '4.00']);
    TimeEntry::factory()->for($this->user)->for($this->project)->running()->create(['spent_on' => '2026-09-09']);
    TimeEntry::factory()->for($this->user)->for($this->project)->count(2)->create(['spent_on' => '2026-09-10']);
    $someoneElses = Project::factory()->create();

    Sanctum::actingAs($this->user);

    foreach (['2026-09-08', '2026-09-09', '2026-09-10'] as $date) {
        $this->postJson(route('desktop.week.cell'), ['project_id' => $this->project->id, 'spent_on' => $date, 'hours' => '1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hours');
    }

    $this->postJson(route('desktop.week.cell'), ['project_id' => $someoneElses->id, 'spent_on' => '2026-09-08', 'hours' => '1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('project_id');

    $this->postJson(route('desktop.week.cell'), ['project_id' => $this->project->id, 'spent_on' => '08-09-2026', 'hours' => '25'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['spent_on', 'hours']);

    expect(TimeEntry::query()->whereDate('spent_on', '2026-09-08')->sole()->hours)->toBe('4.00');
});

it('removes a project row from the week and keeps the hours that are locked or elsewhere', function (): void {
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-07', 'hours' => '2.00']);
    $billed = TimeEntry::factory()->for($this->user)->for($this->project)->billed()->create(['spent_on' => '2026-09-09', 'hours' => '4.00']);
    $nextWeek = TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-14', 'hours' => '1.00']);
    $someoneElse = TimeEntry::factory()->for($this->project)->create(['spent_on' => '2026-09-07', 'hours' => '9.00']);

    Sanctum::actingAs($this->user);

    $this->deleteJson(route('desktop.week.row', ['project_id' => $this->project->id, 'week_start' => '2026-09-07']))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->where('week_total', '4.00')->where('rows.0.is_locked', true)->etc());

    expect(TimeEntry::withoutGlobalScope(UserScope::class)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$billed->id, $nextWeek->id, $someoneElse->id])->sort()->values()->all());
});
