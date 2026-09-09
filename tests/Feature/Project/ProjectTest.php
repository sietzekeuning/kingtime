<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Project\Enums\BillBy;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\ProjectTask;
use App\Domain\Project\Models\Task;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function projectPayload(Client $client, array $overrides = []): array
{
    return [
        'client_id' => $client->id,
        'name' => 'Website',
        'code' => 'WEB',
        'is_billable' => true,
        'bill_by' => BillBy::Project->value,
        'hourly_rate' => '95.00',
        'budget_hours' => '40',
        'is_active' => true,
        'color' => '#F97316',
        'starts_on' => '2026-01-01',
        'ends_on' => null,
        'notes' => null,
        'tasks' => [],
        ...$overrides,
    ];
}

it('lists projects with the table payload, client name and hour totals', function (): void {
    $project = Project::factory()->create(['name' => 'Aardvark']);
    TimeEntry::factory()->for($project)->create(['hours' => '2.50', 'is_billable' => true, 'is_billed' => false]);
    TimeEntry::factory()->for($project)->billed()->create(['hours' => '1.25']);
    Project::factory()->count(2)->sequence(['name' => 'Beta'], ['name' => 'Gamma'])->create();

    $this->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/ProjectList')
            ->has('items.data', 3)
            ->has('items.allowed_sorts')
            ->has('items.allowed_filters')
            ->has('clients', 3)
            ->where('items.data.0.name', 'Aardvark')
            ->where('items.data.0.client.name', $project->client->name)
            ->where('items.data.0.total_hours', fn (string $hours) => (float) $hours === 3.75)
            ->where('items.data.0.unbilled_hours', fn (string $hours) => (float) $hours === 2.5));
});

it('filters projects by client, active status and bill-by', function (): void {
    $acme = Client::factory()->create(['name' => 'Acme']);
    $globex = Client::factory()->create(['name' => 'Globex']);
    Project::factory()->for($acme)->create(['name' => 'Acme site', 'is_active' => true]);
    Project::factory()->for($globex)->create(['name' => 'Globex app', 'is_active' => false, 'bill_by' => BillBy::Task]);

    $this->get(route('projects.index', ['filter' => ['client_id' => $acme->id]]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Acme site'));

    $this->get(route('projects.index', ['filter' => ['is_active' => '0']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Globex app'));

    $this->get(route('projects.index', ['filter' => ['bill_by' => 'task']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Globex app'));
});

it('offers clients and active tasks on the create form', function (): void {
    Client::factory()->create();
    Task::factory()->create(['name' => 'Development', 'is_active' => true]);
    Task::factory()->create(['name' => 'Retired', 'is_active' => false]);

    $this->get(route('projects.create'))
        ->assertInertia(fn ($page) => $page
            ->component('projects/ProjectForm')
            ->where('project.id', null)
            ->where('project.client_id', null)
            ->where('project.tasks', [])
            ->has('clients', 1)
            ->has('tasks', 1)
            ->where('tasks.0.name', 'Development'));
});

it('creates a project with task assignments', function (): void {
    $client = Client::factory()->create();
    [$development, $meeting] = Task::factory()->count(2)->sequence(['name' => 'Development'], ['name' => 'Meeting'])->create();

    $this->post(route('projects.store'), projectPayload($client, [
        'bill_by' => BillBy::Task->value,
        'hourly_rate' => null,
        'tasks' => [
            ['id' => null, 'task_id' => $development->id, 'is_billable' => true, 'hourly_rate' => '110.00', 'is_active' => true],
            ['id' => null, 'task_id' => $meeting->id, 'is_billable' => false, 'hourly_rate' => null, 'is_active' => true],
        ],
    ]))->assertRedirect();

    $project = Project::query()->where('name', 'Website')->firstOrFail();

    expect($project->client_id)->toBe($client->id)
        ->and($project->bill_by)->toBe(BillBy::Task)
        ->and($project->starts_on?->toDateString())->toBe('2026-01-01')
        ->and($project->taskAssignments()->count())->toBe(2);

    $assignment = $project->taskAssignments()->where('task_id', $development->id)->firstOrFail();
    expect($assignment->hourly_rate)->toBe('110.00')->and($assignment->is_billable)->toBeTrue();

    $this->get(route('projects.edit', $project))
        ->assertInertia(fn ($page) => $page
            ->component('projects/ProjectForm')
            ->where('project.client.name', $client->name)
            ->has('project.tasks', 2)
            ->where('project.tasks.0.task_name', 'Development')
            ->has('tasks', 2));
});

it('rejects an invalid project and an invalid task assignment', function (): void {
    $client = Client::factory()->create();

    $this->from(route('projects.create'))
        ->post(route('projects.store'), ['name' => '', 'client_id' => null, 'hourly_rate' => '-1'])
        ->assertSessionHasErrors(['name', 'client_id', 'hourly_rate']);

    $this->from(route('projects.create'))
        ->post(route('projects.store'), projectPayload($client, [
            'tasks' => [['id' => null, 'task_id' => 999, 'is_billable' => true, 'hourly_rate' => null, 'is_active' => true]],
        ]))
        ->assertSessionHasErrors(['tasks.0.task_id']);

    $task = Task::factory()->create();
    $this->from(route('projects.create'))
        ->post(route('projects.store'), projectPayload($client, [
            'tasks' => [
                ['id' => null, 'task_id' => $task->id, 'is_billable' => true, 'hourly_rate' => null, 'is_active' => true],
                ['id' => null, 'task_id' => $task->id, 'is_billable' => true, 'hourly_rate' => null, 'is_active' => true],
            ],
        ]))
        ->assertSessionHasErrors(['tasks.1.task_id']);

    expect(Project::query()->count())->toBe(0);
});

it('updates a project, adding and removing assignments while keeping the harvest id', function (): void {
    $project = Project::factory()->create(['name' => 'Old']);
    [$keep, $drop, $add] = Task::factory()->count(3)->sequence(['name' => 'Keep'], ['name' => 'Drop'], ['name' => 'Add'])->create();
    $kept = ProjectTask::query()->create(['project_id' => $project->id, 'task_id' => $keep->id, 'hourly_rate' => '80.00', 'harvest_id' => 4242]);
    ProjectTask::query()->create(['project_id' => $project->id, 'task_id' => $drop->id]);

    $this->patch(route('projects.update', $project), projectPayload($project->client, [
        'name' => 'New',
        'tasks' => [
            ['id' => $kept->id, 'task_id' => $keep->id, 'is_billable' => false, 'hourly_rate' => '90.00', 'is_active' => false],
            ['id' => null, 'task_id' => $add->id, 'is_billable' => true, 'hourly_rate' => null, 'is_active' => true],
        ],
    ]))->assertRedirect();

    expect($project->fresh()->name)->toBe('New')
        ->and($project->taskAssignments()->pluck('task_id')->all())->toEqualCanonicalizing([$keep->id, $add->id]);

    $kept->refresh();
    expect($kept->harvest_id)->toBe(4242)
        ->and($kept->hourly_rate)->toBe('90.00')
        ->and($kept->is_billable)->toBeFalse()
        ->and($kept->is_active)->toBeFalse();
});

it('deletes a project', function (): void {
    $project = Project::factory()->create();

    $this->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));

    expect(Project::query()->find($project->id))->toBeNull();
});

it('resolves the rate for a task from the project or the assignment', function (): void {
    $task = Task::factory()->create(['default_hourly_rate' => '70.00']);

    $byProject = Project::factory()->create(['bill_by' => BillBy::Project, 'hourly_rate' => '95.00']);
    ProjectTask::query()->create(['project_id' => $byProject->id, 'task_id' => $task->id, 'hourly_rate' => '110.00']);
    expect($byProject->rateForTask($task))->toBe('95.00')
        ->and($byProject->rateForTask(null))->toBe('95.00');

    $byTask = Project::factory()->create(['bill_by' => BillBy::Task, 'hourly_rate' => '95.00']);
    ProjectTask::query()->create(['project_id' => $byTask->id, 'task_id' => $task->id, 'hourly_rate' => '110.00']);
    expect($byTask->rateForTask($task))->toBe('110.00');

    $byTaskDefault = Project::factory()->create(['bill_by' => BillBy::Task, 'hourly_rate' => null]);
    ProjectTask::query()->create(['project_id' => $byTaskDefault->id, 'task_id' => $task->id, 'hourly_rate' => null]);
    expect($byTaskDefault->rateForTask($task))->toBe('70.00');

    $nonBillable = Project::factory()->nonBillable()->create();
    expect($nonBillable->rateForTask($task))->toBeNull();
});
