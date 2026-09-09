<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
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
        'hourly_rate' => '95.00',
        'budget_hours' => '40',
        'budget_amount' => '10000',
        'is_active' => true,
        'color' => '#F97316',
        'starts_on' => '2026-01-01',
        'ends_on' => null,
        'notes' => null,
        ...$overrides,
    ];
}

it('lists projects with the table payload, client name, hour totals and the amount spent', function (): void {
    $project = Project::factory()->create(['name' => 'Aardvark', 'budget_amount' => '1000.00']);
    TimeEntry::factory()->for($project)->create(['hours' => '2.50', 'hourly_rate' => '80.00', 'is_billable' => true, 'is_billed' => false]);
    TimeEntry::factory()->for($project)->billed()->create(['hours' => '1.25', 'hourly_rate' => '100.00']);
    TimeEntry::factory()->for($project)->billed()->create(['hours' => '4.00', 'hourly_rate' => null]);
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
            ->where('items.data.0.total_hours', fn (string $hours) => (float) $hours === 7.75)
            ->where('items.data.0.unbilled_hours', fn (string $hours) => (float) $hours === 2.5)
            ->where('items.data.0.budget_amount', '1000.00')
            ->where('items.data.0.spent_amount', fn (string $amount) => (float) $amount === 325.0));
});

it('filters projects by client, active status and billability', function (): void {
    $acme = Client::factory()->create(['name' => 'Acme']);
    $globex = Client::factory()->create(['name' => 'Globex']);
    Project::factory()->for($acme)->create(['name' => 'Acme site', 'is_active' => true]);
    Project::factory()->for($globex)->nonBillable()->create(['name' => 'Globex app', 'is_active' => false]);

    $this->get(route('projects.index', ['filter' => ['client_id' => $acme->id]]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Acme site'));

    $this->get(route('projects.index', ['filter' => ['is_active' => '0']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Globex app'));

    $this->get(route('projects.index', ['filter' => ['is_billable' => '0', 'is_active' => 'all']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Globex app'));
});

it('offers active clients on the create form', function (): void {
    Client::factory()->create(['name' => 'Active']);
    Client::factory()->create(['name' => 'Retired', 'is_active' => false]);

    $this->get(route('projects.create'))
        ->assertInertia(fn ($page) => $page
            ->component('projects/ProjectForm')
            ->where('project.id', null)
            ->where('project.client_id', null)
            ->has('clients', 1)
            ->where('clients.0.name', 'Active'));
});

it('creates a project from a validated payload', function (): void {
    $client = Client::factory()->create();

    $this->post(route('projects.store'), projectPayload($client))->assertRedirect();

    $project = Project::query()->where('name', 'Website')->firstOrFail();

    expect($project->client_id)->toBe($client->id)
        ->and($project->hourly_rate)->toBe('95.00')
        ->and($project->budget_hours)->toBe('40.00')
        ->and($project->budget_amount)->toBe('10000.00')
        ->and($project->starts_on?->toDateString())->toBe('2026-01-01');

    $this->get(route('projects.edit', $project))
        ->assertInertia(fn ($page) => $page
            ->component('projects/ProjectForm')
            ->where('project.name', 'Website')
            ->where('project.client.name', $client->name));
});

it('rejects an invalid project', function (): void {
    $this->from(route('projects.create'))
        ->post(route('projects.store'), ['name' => '', 'client_id' => null, 'hourly_rate' => '-1'])
        ->assertSessionHasErrors(['name', 'client_id', 'hourly_rate']);

    expect(Project::query()->count())->toBe(0);
});

it('updates a project', function (): void {
    $project = Project::factory()->create(['name' => 'Old', 'harvest_id' => 4242]);

    $this->patch(route('projects.update', $project), projectPayload($project->client, [
        'name' => 'New',
        'is_billable' => false,
        'hourly_rate' => null,
        'harvest_id' => 4242,
    ]))->assertRedirect();

    $project->refresh();
    expect($project->name)->toBe('New')
        ->and($project->is_billable)->toBeFalse()
        ->and($project->hourly_rate)->toBeNull()
        ->and($project->harvest_id)->toBe(4242);
});

it('deletes a project', function (): void {
    $project = Project::factory()->create();

    $this->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));

    expect(Project::query()->find($project->id))->toBeNull();
});

it('renders the edit form for an imported project without optional fields', function (): void {
    $project = Project::factory()->create([
        'code' => null,
        'color' => null,
        'hourly_rate' => null,
        'budget_hours' => null,
        'budget_amount' => null,
        'starts_on' => null,
        'ends_on' => null,
        'notes' => '',
        'harvest_id' => 49054616,
    ]);

    $this->get(route('projects.edit', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/ProjectForm')
            ->where('project.id', $project->id)
            ->where('project.code', null)
            ->where('project.color', null)
            ->where('project.hourly_rate', null)
            ->where('project.budget_hours', null)
            ->where('project.budget_amount', null)
            ->where('project.spent_amount', null)
            ->where('project.starts_on', null)
            ->where('project.ends_on', null)
            ->where('project.total_hours', null)
            ->where('project.harvest_id', 49054616)
            ->where('project.client.name', $project->client->name));
});

it('bills new entries at the project rate only when the project is billable', function (): void {
    $billable = Project::factory()->create(['hourly_rate' => '95.00']);
    $unpriced = Project::factory()->create(['hourly_rate' => null]);
    $nonBillable = Project::factory()->create(['is_billable' => false, 'hourly_rate' => '95.00']);

    expect($billable->billableRate())->toBe('95.00')
        ->and($unpriced->billableRate())->toBeNull()
        ->and($nonBillable->billableRate())->toBeNull();
});
