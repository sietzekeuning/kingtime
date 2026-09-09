<?php

declare(strict_types=1);

use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->project = Project::factory()->create(['hourly_rate' => '95.00', 'is_billable' => true]);
});

it('creates an entry from an empty cell with the project defaults', function (): void {
    $this->from(route('time-entries.index', ['date' => '2026-09-09']))
        ->post(route('timesheet.cells.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-08', 'hours' => '2.5'])
        ->assertRedirect(route('time-entries.index', ['date' => '2026-09-09']));

    $entry = TimeEntry::query()->sole();
    expect($entry->user_id)->toBe($this->user->id)
        ->and($entry->spent_on->toDateString())->toBe('2026-09-08')
        ->and($entry->hours)->toBe('2.50')
        ->and($entry->hourly_rate)->toBe('95.00')
        ->and($entry->is_billable)->toBeTrue();
});

it('updates the single entry behind a cell and deletes it when cleared', function (): void {
    $entry = TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-08', 'hours' => '1.00']);

    $this->post(route('timesheet.cells.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-08', 'hours' => '3'])
        ->assertRedirect();

    expect($entry->fresh()?->hours)->toBe('3.00')
        ->and(TimeEntry::query()->count())->toBe(1);

    $this->post(route('timesheet.cells.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-08', 'hours' => null])
        ->assertRedirect();

    expect(TimeEntry::query()->find($entry->id))->toBeNull();
});

it('refuses cells with several entries, a running timer or a locked entry', function (): void {
    TimeEntry::factory()->for($this->user)->for($this->project)->count(2)->create(['spent_on' => '2026-09-08']);
    TimeEntry::factory()->for($this->user)->for($this->project)->running()->create(['spent_on' => '2026-09-09']);
    TimeEntry::factory()->for($this->user)->for($this->project)->billed()->create(['spent_on' => '2026-09-10', 'hours' => '4.00']);

    $this->post(route('timesheet.cells.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-08', 'hours' => '1'])
        ->assertSessionHasErrors('hours');

    $this->post(route('timesheet.cells.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-09', 'hours' => '1'])
        ->assertSessionHasErrors('hours');

    $this->from(route('time-entries.index'))
        ->post(route('timesheet.cells.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-10', 'hours' => '1'])
        ->assertRedirect(route('time-entries.index'))
        ->assertSessionHas('toast.type', 'error');

    expect(TimeEntry::query()->whereDate('spent_on', '2026-09-10')->sole()->hours)->toBe('4.00');
});

it('never touches another user\'s entry in the same cell', function (): void {
    $other = TimeEntry::factory()->for($this->project)->create(['spent_on' => '2026-09-08', 'hours' => '7.00']);

    $this->post(route('timesheet.cells.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-08', 'hours' => '1'])
        ->assertRedirect();

    expect($other->fresh()?->hours)->toBe('7.00')
        ->and(TimeEntry::query()->count())->toBe(2);
});

it('validates the cell payload', function (): void {
    $this->post(route('timesheet.cells.store'), ['project_id' => 999, 'spent_on' => '08-09-2026', 'hours' => '25'])
        ->assertSessionHasErrors(['project_id', 'spent_on', 'hours']);
});

it('deletes the open entries of a project row for the week and keeps the locked ones', function (): void {
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-07']);
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-13']);
    $locked = TimeEntry::factory()->for($this->user)->for($this->project)->billed()->create(['spent_on' => '2026-09-09']);
    $nextWeek = TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-14']);
    $otherProject = TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-09']);
    $otherUser = TimeEntry::factory()->for($this->project)->create(['spent_on' => '2026-09-09']);

    $this->delete(route('timesheet.rows.destroy', ['project_id' => $this->project->id, 'week_start' => '2026-09-09']))
        ->assertRedirect()
        ->assertSessionHas('toast.message', '2 time entries deleted.');

    expect(TimeEntry::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$locked->id, $nextWeek->id, $otherProject->id, $otherUser->id])->sort()->values()->all());
});
