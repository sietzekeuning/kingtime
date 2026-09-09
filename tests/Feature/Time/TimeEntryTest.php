<?php

declare(strict_types=1);

use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the timesheet with the table payload and the select options', function (): void {
    $project = Project::factory()->create();
    TimeEntry::factory()->count(2)->for($this->user)->for($project)->create();

    $this->get(route('time-entries.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('time-entries/TimeEntryList')
            ->has('timesheet.days', 7)
            ->has('items.data', 2)
            ->has('items.allowed_filters')
            ->has('projects', 1)
            ->where('projects.0.client_name', $project->client->name)
            ->has('clients', 1));
});

it('rejects a malformed date on the timesheet', function (): void {
    $this->from(route('dashboard'))
        ->get(route('time-entries.index', ['date' => 'yesterday']))
        ->assertSessionHasErrors('date');
});

it('filters the table by project and client', function (): void {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    TimeEntry::factory()->for($this->user)->for($projectA)->create();
    TimeEntry::factory()->for($this->user)->for($projectB)->create();

    $this->get(route('time-entries.index', ['filter' => ['project_id' => $projectA->id]]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.project_id', $projectA->id));

    $this->get(route('time-entries.index', ['filter' => ['client_id' => $projectB->client_id]]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.project_id', $projectB->id));
});

it('logs time with the project rate when no rate is given', function (): void {
    $project = Project::factory()->create(['hourly_rate' => '95.00']);

    $this->post(route('time-entries.store'), [
        'project_id' => $project->id,
        'spent_on' => '2026-09-09',
        'hours' => '1.5',
        'notes' => 'Refactoring',
    ])->assertRedirect(route('time-entries.index', ['date' => '2026-09-09']));

    $entry = TimeEntry::query()->sole();
    expect($entry->user_id)->toBe($this->user->id)
        ->and($entry->hours)->toBe('1.50')
        ->and($entry->hourly_rate)->toBe('95.00')
        ->and($entry->is_billable)->toBeTrue();
});

it('keeps an explicit rate and billable flag', function (): void {
    $project = Project::factory()->nonBillable()->create();

    $this->post(route('time-entries.store'), [
        'project_id' => $project->id,
        'spent_on' => '2026-09-09',
        'hours' => '1',
        'hourly_rate' => '50',
        'is_billable' => true,
    ])->assertRedirect();

    $entry = TimeEntry::query()->sole();
    expect($entry->hourly_rate)->toBe('50.00')->and($entry->is_billable)->toBeTrue();
});

it('defaults billability and rate from the project', function (): void {
    $billable = Project::factory()->create(['hourly_rate' => '95.00']);
    $unpriced = Project::factory()->create(['hourly_rate' => null]);
    $free = Project::factory()->create(['is_billable' => false, 'hourly_rate' => '95.00']);

    foreach ([$billable, $unpriced, $free] as $project) {
        $this->post(route('time-entries.store'), ['project_id' => $project->id, 'spent_on' => '2026-09-09', 'hours' => '1'])->assertRedirect();
    }

    $entries = TimeEntry::query()->orderBy('id')->get();
    expect($entries[0]->is_billable)->toBeTrue()
        ->and($entries[0]->hourly_rate)->toBe('95.00')
        ->and($entries[1]->is_billable)->toBeTrue()
        ->and($entries[1]->hourly_rate)->toBeNull()
        ->and($entries[2]->is_billable)->toBeFalse()
        ->and($entries[2]->hourly_rate)->toBeNull();
});

it('can start the timer straight from the new entry form', function (): void {
    $project = Project::factory()->create();

    $this->post(route('time-entries.store'), [
        'project_id' => $project->id,
        'spent_on' => '2026-09-09',
        'hours' => '0',
        'start_timer' => true,
    ])->assertRedirect();

    $entry = TimeEntry::query()->sole();
    expect($entry->is_running)->toBeTrue()->and($entry->timer_started_at)->not->toBeNull();
});

it('rejects an invalid entry', function (): void {
    $this->from(route('time-entries.index'))
        ->post(route('time-entries.store'), ['project_id' => 999, 'spent_on' => 'nope', 'hours' => '25'])
        ->assertSessionHasErrors(['project_id', 'spent_on', 'hours']);
});

it('updates an entry and re-derives the rate when the project changes', function (): void {
    $project = Project::factory()->create(['hourly_rate' => '95.00']);
    $otherProject = Project::factory()->create(['hourly_rate' => '150.00']);
    $freeProject = Project::factory()->nonBillable()->create();
    $entry = TimeEntry::factory()->for($this->user)->for($project)->create(['hours' => '1.00', 'hourly_rate' => '95.00']);

    $this->patch(route('time-entries.update', $entry), [
        'project_id' => $project->id,
        'spent_on' => '2026-09-10',
        'hours' => '2.25',
        'notes' => 'Changed',
    ])->assertRedirect();

    $entry->refresh();
    expect($entry->hours)->toBe('2.25')
        ->and($entry->spent_on->toDateString())->toBe('2026-09-10')
        ->and($entry->notes)->toBe('Changed')
        ->and($entry->hourly_rate)->toBe('95.00');

    $this->patch(route('time-entries.update', $entry), [
        'project_id' => $otherProject->id,
        'spent_on' => '2026-09-10',
        'hours' => '2.25',
    ])->assertRedirect();

    $entry->refresh();
    expect($entry->project_id)->toBe($otherProject->id)
        ->and($entry->hourly_rate)->toBe('150.00')
        ->and($entry->is_billable)->toBeTrue();

    $this->patch(route('time-entries.update', $entry), [
        'project_id' => $freeProject->id,
        'spent_on' => '2026-09-10',
        'hours' => '2.25',
    ])->assertRedirect();

    $entry->refresh();
    expect($entry->project_id)->toBe($freeProject->id)
        ->and($entry->hourly_rate)->toBeNull()
        ->and($entry->is_billable)->toBeFalse();
});

it('deletes an entry and returns to its day', function (): void {
    $entry = TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-09']);

    $this->delete(route('time-entries.destroy', $entry))
        ->assertRedirect(route('time-entries.index', ['date' => '2026-09-09']));

    expect(TimeEntry::query()->find($entry->id))->toBeNull();
});

it('refuses to change or delete billed and locked entries', function (): void {
    $billed = TimeEntry::factory()->for($this->user)->billed()->create(['hours' => '1.00']);
    $locked = TimeEntry::factory()->for($this->user)->create(['is_locked' => true, 'hours' => '1.00']);

    foreach ([$billed, $locked] as $entry) {
        $this->from(route('time-entries.edit', $entry))
            ->patch(route('time-entries.update', $entry), [...$entry->toArray(), 'hours' => '5'])
            ->assertRedirect(route('time-entries.edit', $entry))
            ->assertSessionHas('toast.type', 'error');

        $this->from(route('time-entries.index'))
            ->delete(route('time-entries.destroy', $entry))
            ->assertRedirect(route('time-entries.index'))
            ->assertSessionHas('toast.type', 'error');

        expect($entry->fresh()->hours)->toBe('1.00');
    }
});

it('hides the entries of other users completely', function (): void {
    $entry = TimeEntry::factory()->create();

    $this->get(route('time-entries.index'))
        ->assertInertia(fn ($page) => $page->has('items.data', 0));

    $this->get(route('time-entries.edit', $entry))->assertNotFound();
    $this->patch(route('time-entries.update', $entry), [...$entry->toArray(), 'hours' => '5'])->assertNotFound();
    $this->delete(route('time-entries.destroy', $entry))->assertNotFound();
    $this->post(route('time-entries.start', $entry))->assertNotFound();
    $this->post(route('time-entries.stop', $entry))->assertNotFound();

    expect($entry->fresh()?->hours)->toBe($entry->hours);
});
