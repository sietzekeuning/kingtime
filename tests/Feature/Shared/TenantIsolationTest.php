<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Actions\PrepareInvoiceAction;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Mcp\Servers\KingtimeServer;
use App\Domain\Mcp\Tools\ListClientsTool;
use App\Domain\Mcp\Tools\ListProjectsTool;
use App\Domain\Mcp\Tools\LogTimeTool;
use App\Domain\Project\Models\Project;
use App\Domain\Shared\Models\Scopes\UserScope;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Every user is a tenant of their own: what one user makes, another never
 * sees, lists, edits or links to.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create(['name' => 'Owner']);
    $this->intruder = User::factory()->create(['name' => 'Intruder']);

    $this->client = Client::factory()->for($this->owner)->create(['name' => 'Owner client']);
    $this->project = Project::factory()->for($this->client)->create(['name' => 'Owner project']);
    $this->entry = TimeEntry::factory()->for($this->owner)->for($this->project)->create(['spent_on' => '2026-08-03', 'hours' => '2.00', 'hourly_rate' => '95.00']);
    $this->invoice = app(PrepareInvoiceAction::class)->handle($this->owner, $this->client, Date::parse('2026-08-01'), Date::parse('2026-08-31'));

    $this->actingAs($this->intruder);
});

it('gives every row the authenticated user as its owner', function (): void {
    $client = Client::query()->create(['name' => 'Mine']);
    $project = Project::query()->create(['client_id' => $client->id, 'name' => 'Mine too']);

    expect($client->user_id)->toBe($this->intruder->id)
        ->and($project->user_id)->toBe($this->intruder->id)
        ->and(Project::query()->create(['client_id' => $this->client->id, 'name' => 'On someone else\'s client'])->user_id)
        ->toBe($this->owner->id);
});

it('keeps the lists, the dashboard and the reports empty for another user', function (): void {
    $this->get(route('clients.index'))->assertInertia(fn ($page) => $page->has('items.data', 0));
    $this->get(route('projects.index'))->assertInertia(fn ($page) => $page->has('items.data', 0)->has('clients', 0));
    $this->get(route('time-entries.index'))->assertInertia(fn ($page) => $page
        ->has('items.data', 0)
        ->has('projects', 0)
        ->has('clients', 0)
        ->where('timesheet.week_total', '0.00'));
    $this->get(route('invoices.index'))->assertInertia(fn ($page) => $page->has('items.data', 0));
    $this->get(route('invoices.prepare.create'))->assertInertia(fn ($page) => $page->has('clients', 0));
    $this->get(route('reports.index'))->assertInertia(fn ($page) => $page->where('reports.totals.hours', '0.00'));
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('recent_entries', 0)->has('top_projects', 0));
});

it('answers not found for another user\'s records', function (): void {
    $this->get(route('clients.edit', $this->client))->assertNotFound();
    $this->patch(route('clients.update', $this->client), ['name' => 'Taken over'])->assertNotFound();
    $this->delete(route('clients.destroy', $this->client))->assertNotFound();
    $this->post(route('clients.archive.store', $this->client))->assertNotFound();

    $this->get(route('projects.edit', $this->project))->assertNotFound();
    $this->delete(route('projects.destroy', $this->project))->assertNotFound();
    $this->post(route('projects.archive.store', $this->project))->assertNotFound();

    $this->get(route('time-entries.edit', $this->entry))->assertNotFound();
    $this->delete(route('time-entries.destroy', $this->entry))->assertNotFound();
    $this->post(route('time-entries.start', $this->entry))->assertNotFound();

    $this->get(route('invoices.show', $this->invoice))->assertNotFound();
    $this->delete(route('invoices.destroy', $this->invoice))->assertNotFound();
    $this->post(route('invoices.push', $this->invoice))->assertNotFound();

    expect($this->client->fresh()?->name)->toBe('Owner client')
        ->and($this->invoice->fresh())->not->toBeNull();
});

it('refuses to link new records to another user\'s client, project or hours', function (): void {
    $this->from(route('projects.create'))
        ->post(route('projects.store'), ['client_id' => $this->client->id, 'name' => 'Hijack'])
        ->assertSessionHasErrors('client_id');

    $this->from(route('time-entries.create'))
        ->post(route('time-entries.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-01', 'hours' => '1'])
        ->assertSessionHasErrors('project_id');

    $this->post(route('timesheet.cells.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-01', 'hours' => '1'])
        ->assertSessionHasErrors('project_id');

    $this->from(route('invoices.prepare.create'))
        ->post(route('invoices.prepare.store'), ['client_id' => $this->client->id, 'period_starts_on' => '2026-08-01', 'period_ends_on' => '2026-08-31'])
        ->assertSessionHasErrors('client_id');

    $this->get(route('reports.index', ['client_id' => $this->client->id]))->assertSessionHasErrors('client_id');

    expect(Project::withoutGlobalScope(UserScope::class)->count())->toBe(1)
        ->and(TimeEntry::withoutGlobalScope(UserScope::class)->count())->toBe(1)
        ->and(Invoice::withoutGlobalScope(UserScope::class)->count())->toBe(1);
});

it('scopes the mcp tools to the token owner', function (): void {
    KingtimeServer::actingAs($this->intruder)->tool(ListClientsTool::class)
        ->assertStructuredContent(fn (AssertableJson $json) => $json->has('clients', 0));
    KingtimeServer::actingAs($this->intruder)->tool(ListProjectsTool::class)
        ->assertStructuredContent(fn (AssertableJson $json) => $json->has('projects', 0));
    KingtimeServer::actingAs($this->intruder)->tool(LogTimeTool::class, ['project_id' => $this->project->id, 'hours' => 1])
        ->assertHasErrors(["No project with id {$this->project->id}"]);

    expect(TimeEntry::withoutGlobalScope(UserScope::class)->count())->toBe(1);
});

it('lets the owner keep working with their own data', function (): void {
    $this->actingAs($this->owner);

    $this->get(route('clients.index'))->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Owner client'));
    $this->get(route('projects.edit', $this->project))->assertOk();
    $this->get(route('invoices.show', $this->invoice))->assertOk();
    $this->post(route('time-entries.store'), ['project_id' => $this->project->id, 'spent_on' => '2026-09-01', 'hours' => '1'])
        ->assertSessionHasNoErrors();

    expect(TimeEntry::query()->count())->toBe(2);
});
