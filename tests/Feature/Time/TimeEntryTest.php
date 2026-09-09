<?php

declare(strict_types=1);

use App\Domain\Project\Enums\BillBy;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\Task;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * @return array{0: Project, 1: Task}
 */
function timeEntryProjectWithTask(array $projectAttributes = [], array $assignment = []): array
{
    $project = Project::factory()->create($projectAttributes);
    $task = Task::factory()->create();
    $project->tasks()->attach($task->id, [...['is_billable' => true, 'hourly_rate' => null, 'is_active' => true], ...$assignment]);

    return [$project, $task];
}

it('renders the timesheet with the table payload and the select options', function (): void {
    [$project] = timeEntryProjectWithTask();
    TimeEntry::factory()->count(2)->for($this->user)->for($project)->create();
    TimeEntry::factory()->for($project)->create();

    $this->get(route('time-entries.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('time-entries/TimeEntryList')
            ->has('timesheet.days', 7)
            ->has('items.data', 3)
            ->has('items.allowed_filters')
            ->has('projects', 1)
            ->has('projects.0.tasks', 1)
            ->has('clients', 1));
});

it('rejects a malformed date on the timesheet', function (): void {
    $this->from(route('dashboard'))
        ->get(route('time-entries.index', ['date' => 'yesterday']))
        ->assertSessionHasErrors('date');
});

it('filters the table by project and client', function (): void {
    [$projectA] = timeEntryProjectWithTask();
    [$projectB] = timeEntryProjectWithTask();
    TimeEntry::factory()->for($this->user)->for($projectA)->create();
    TimeEntry::factory()->for($this->user)->for($projectB)->create();

    $this->get(route('time-entries.index', ['filter' => ['project_id' => $projectA->id]]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.project_id', $projectA->id));

    $this->get(route('time-entries.index', ['filter' => ['client_id' => $projectB->client_id]]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.project_id', $projectB->id));
});

it('logs time with the project rate when no rate is given', function (): void {
    [$project, $task] = timeEntryProjectWithTask(['hourly_rate' => '95.00']);

    $this->post(route('time-entries.store'), [
        'project_id' => $project->id,
        'task_id' => $task->id,
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

it('takes the task assignment rate when the project bills by task', function (): void {
    [$project, $task] = timeEntryProjectWithTask(['bill_by' => BillBy::Task, 'hourly_rate' => '95.00'], ['hourly_rate' => '120.00']);

    $this->post(route('time-entries.store'), [
        'project_id' => $project->id,
        'task_id' => $task->id,
        'spent_on' => '2026-09-09',
        'hours' => '2',
    ])->assertRedirect();

    expect(TimeEntry::query()->sole()->hourly_rate)->toBe('120.00');
});

it('keeps an explicit rate and billable flag', function (): void {
    [$project, $task] = timeEntryProjectWithTask(['hourly_rate' => '95.00'], ['is_billable' => false]);

    $this->post(route('time-entries.store'), [
        'project_id' => $project->id,
        'task_id' => $task->id,
        'spent_on' => '2026-09-09',
        'hours' => '1',
        'hourly_rate' => '50',
        'is_billable' => true,
    ])->assertRedirect();

    $entry = TimeEntry::query()->sole();
    expect($entry->hourly_rate)->toBe('50.00')->and($entry->is_billable)->toBeTrue();
});

it('defaults billability from the task assignment and the project', function (): void {
    [$project, $task] = timeEntryProjectWithTask([], ['is_billable' => false]);
    [$freeProject, $freeTask] = timeEntryProjectWithTask(['is_billable' => false, 'bill_by' => BillBy::None, 'hourly_rate' => null]);

    $this->post(route('time-entries.store'), ['project_id' => $project->id, 'task_id' => $task->id, 'spent_on' => '2026-09-09', 'hours' => '1'])->assertRedirect();
    $this->post(route('time-entries.store'), ['project_id' => $freeProject->id, 'task_id' => $freeTask->id, 'spent_on' => '2026-09-09', 'hours' => '1'])->assertRedirect();

    $entries = TimeEntry::query()->orderBy('id')->get();
    expect($entries[0]->is_billable)->toBeFalse()
        ->and($entries[0]->hourly_rate)->toBe('95.00')
        ->and($entries[1]->is_billable)->toBeFalse()
        ->and($entries[1]->hourly_rate)->toBeNull();
});

it('can start the timer straight from the new entry form', function (): void {
    [$project, $task] = timeEntryProjectWithTask();

    $this->post(route('time-entries.store'), [
        'project_id' => $project->id,
        'task_id' => $task->id,
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
    [$project, $task] = timeEntryProjectWithTask(['hourly_rate' => '95.00']);
    [$otherProject, $otherTask] = timeEntryProjectWithTask(['hourly_rate' => '150.00'], ['is_billable' => false]);
    $entry = TimeEntry::factory()->for($this->user)->for($project)->for($task)->create(['hours' => '1.00', 'hourly_rate' => '95.00']);

    $this->patch(route('time-entries.update', $entry), [
        'project_id' => $project->id,
        'task_id' => $task->id,
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
        'task_id' => $otherTask->id,
        'spent_on' => '2026-09-10',
        'hours' => '2.25',
    ])->assertRedirect();

    $entry->refresh();
    expect($entry->project_id)->toBe($otherProject->id)
        ->and($entry->hourly_rate)->toBe('150.00')
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

it('shows other users their entries but lets only the owner change them', function (): void {
    $entry = TimeEntry::factory()->create();

    $this->get(route('time-entries.index'))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.id', $entry->id));

    $this->get(route('time-entries.edit', $entry))->assertForbidden();
    $this->patch(route('time-entries.update', $entry), [...$entry->toArray(), 'hours' => '5'])->assertForbidden();
    $this->delete(route('time-entries.destroy', $entry))->assertForbidden();
    $this->post(route('time-entries.start', $entry))->assertForbidden();
    $this->post(route('time-entries.stop', $entry))->assertForbidden();
});
