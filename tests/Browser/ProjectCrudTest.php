<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\User\Models\User;

/**
 * The project form through Chromium. The client picker is a reka-ui Select
 * and billable is a Switch, both of which only exist in the browser.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates a project for a picked client', function (): void {
    $client = Client::factory()->create(['name' => 'Acme']);

    $page = visit('/projects/create');

    $page->assertSee('New project');
    $page->click('[data-field="client_id"] button[role="combobox"]');
    browserWaitUntilSee($page, 'Acme');
    $page->click('[role="option"]:has-text("Acme")');
    $page->fill('[data-field="name"] input', 'Website');
    $page->fill('[data-field="code"] input', 'WEB');
    $page->fill('[data-field="hourly_rate"] input', '95');
    $page->press('Save');

    browserWaitUntilSee($page, 'Project created.');

    $project = Project::query()->where('name', 'Website')->sole();

    expect($project->client_id)->toBe($client->id)
        ->and($project->code)->toBe('WEB')
        ->and($project->hourly_rate)->toBe('95.00')
        ->and($project->is_billable)->toBeTrue()
        ->and($project->user_id)->toBe($this->user->id);

    $page->assertPathIs("/projects/{$project->id}/edit")->assertNoJavaScriptErrors();
});

it('requires a client before a project is saved', function (): void {
    $page = visit('/projects/create');

    $page->fill('[data-field="name"] input', 'Website');
    $page->press('Save');

    browserWaitUntilSee($page, 'The client id field is required.');
    $page->assertSeeIn('[data-field="client_id"]', 'The client id field is required.');

    expect(Project::query()->count())->toBe(0);
});

it('edits a project and switches billing off', function (): void {
    $project = Project::factory()->create(['name' => 'Old name', 'is_billable' => true]);

    $page = visit("/projects/{$project->id}/edit");

    $page->assertSee('Old name');
    $page->fill('[data-field="name"] input', 'New name');
    $page->click('[data-field="is_billable"] button[role="switch"]');
    $page->press('Save');

    browserWaitUntilSee($page, 'Project saved.');

    $project->refresh();

    expect($project->name)->toBe('New name')
        ->and($project->is_billable)->toBeFalse();

    $page->assertNoJavaScriptErrors();
});

it('deletes a project from its form after confirming', function (): void {
    $project = Project::factory()->create(['name' => 'Website']);

    $page = visit("/projects/{$project->id}/edit");

    $page->click('Delete');
    browserConfirmDelete($page, 'Delete project "Website"?');

    browserWaitUntilSee($page, 'Project deleted.');
    $page->assertPathIs('/projects');

    expect(Project::query()->find($project->id))->toBeNull();
});
