<?php

declare(strict_types=1);

use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('hides archived projects from the list unless the filter asks for them', function (): void {
    Project::factory()->create(['name' => 'Running', 'is_active' => true]);
    Project::factory()->create(['name' => 'Finished', 'is_active' => false]);

    $this->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Running'));

    $this->get(route('projects.index', ['filter' => ['is_active' => '0']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Finished'));

    $this->get(route('projects.index', ['filter' => ['is_active' => 'all']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 2));
});

it('archives and restores a project without touching its hours', function (): void {
    $project = Project::factory()->create(['is_active' => true]);
    TimeEntry::factory()->for($project)->create(['hours' => '2.00']);

    $this->from(route('projects.edit', $project))
        ->post(route('projects.archive.store', $project))
        ->assertRedirect(route('projects.edit', $project))
        ->assertSessionHas('toast.message', 'Project archived.');

    expect($project->refresh()->is_active)->toBeFalse()
        ->and($project->timeEntries()->count())->toBe(1);

    $this->delete(route('projects.archive.destroy', $project))
        ->assertSessionHas('toast.message', 'Project restored.');

    expect($project->refresh()->is_active)->toBeTrue();
});

it('keeps an archived project selectable while editing an entry that belongs to it', function (): void {
    $archived = Project::factory()->create(['name' => 'Old', 'is_active' => false]);
    Project::factory()->create(['name' => 'Current', 'is_active' => true]);
    $entry = TimeEntry::factory()->for($archived)->create(['user_id' => auth()->id()]);

    $this->get(route('time-entries.create'))
        ->assertInertia(fn ($page) => $page->has('projects', 1)->where('projects.0.name', 'Current'));

    $this->get(route('time-entries.edit', $entry))
        ->assertInertia(fn ($page) => $page->has('projects', 2));
});
