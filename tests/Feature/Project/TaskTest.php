<?php

declare(strict_types=1);

use App\Domain\Project\Models\Task;
use App\Domain\User\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('lists tasks with the table payload', function (): void {
    Task::factory()->count(3)->create();

    $this->get(route('tasks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/TaskList')
            ->has('items.data', 3)
            ->has('items.allowed_sorts')
            ->has('items.allowed_filters'));
});

it('filters tasks by name and active status', function (): void {
    Task::factory()->create(['name' => 'Development', 'is_active' => true]);
    Task::factory()->create(['name' => 'Design', 'is_active' => false]);

    $this->get(route('tasks.index', ['filter' => ['name' => 'Des']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Design'));

    $this->get(route('tasks.index', ['filter' => ['is_active' => '1']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Development'));
});

it('creates a task from a validated payload', function (): void {
    $this->post(route('tasks.store'), [
        'name' => 'Development',
        'is_billable_by_default' => true,
        'default_hourly_rate' => '95.00',
        'is_active' => true,
    ])->assertRedirect();

    $task = Task::query()->where('name', 'Development')->firstOrFail();

    expect($task->default_hourly_rate)->toBe('95.00')
        ->and($task->is_billable_by_default)->toBeTrue();

    $this->get(route('tasks.edit', $task))
        ->assertInertia(fn ($page) => $page->component('tasks/TaskForm')->where('task.name', 'Development'));
});

it('rejects an invalid task', function (): void {
    $this->from(route('tasks.create'))
        ->post(route('tasks.store'), ['name' => '', 'default_hourly_rate' => '-5'])
        ->assertSessionHasErrors(['name', 'default_hourly_rate']);
});

it('updates and deletes a task', function (): void {
    $task = Task::factory()->create(['name' => 'Old']);

    $this->patch(route('tasks.update', $task), [...$task->toArray(), 'name' => 'New', 'is_active' => false])->assertRedirect();
    expect($task->fresh()->name)->toBe('New')
        ->and($task->fresh()->is_active)->toBeFalse();

    $this->delete(route('tasks.destroy', $task))->assertRedirect(route('tasks.index'));
    expect(Task::query()->find($task->id))->toBeNull();
});
