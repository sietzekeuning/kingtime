<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Mcp\Servers\KingtimeServer;
use App\Domain\Mcp\Tools\DeleteTimeEntryTool;
use App\Domain\Mcp\Tools\GetRunningTimerTool;
use App\Domain\Mcp\Tools\GetTimesheetTool;
use App\Domain\Mcp\Tools\ListClientsTool;
use App\Domain\Mcp\Tools\ListProjectsTool;
use App\Domain\Mcp\Tools\ListTimeEntriesTool;
use App\Domain\Mcp\Tools\LogTimeTool;
use App\Domain\Mcp\Tools\StartTimerTool;
use App\Domain\Mcp\Tools\StopTimerTool;
use App\Domain\Mcp\Tools\UpdateTimeEntryTool;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;

/**
 * @param  class-string<Tool>  $tool
 * @param  array<string, mixed>  $arguments
 */
function mcpTimeTool(string $tool, array $arguments = []): TestResponse
{
    return KingtimeServer::actingAs(test()->user)->tool($tool, $arguments);
}

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-09 10:00:00'));
    $this->user = User::factory()->create();
    $this->client = Client::factory()->create(['name' => 'Acme Corporation']);
    $this->project = Project::factory()->for($this->client)->create(['name' => 'Website redesign', 'code' => 'WEB', 'hourly_rate' => '95.00']);
});

it('lists active clients', function (): void {
    Client::factory()->create(['name' => 'Old client', 'is_active' => false]);

    mcpTimeTool(ListClientsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('clients', 1)
            ->where('clients.0.id', $this->client->id)
            ->where('clients.0.name', 'Acme Corporation')
            ->where('clients.0.currency', 'EUR'));

    mcpTimeTool(ListClientsTool::class, ['include_inactive' => true])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->has('clients', 2));
});

it('lists projects with their client and billing settings', function (): void {
    $logo = Project::factory()->for(Client::factory()->create(['name' => 'Beta BV']))->nonBillable()->create(['name' => 'Logo']);
    Project::factory()->for($this->client)->create(['name' => 'Archived', 'is_active' => false]);

    mcpTimeTool(ListProjectsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('projects', 2)
            ->where('projects.0.name', 'Logo')
            ->where('projects.0.client', 'Beta BV')
            ->where('projects.0.is_billable', false)
            ->where('projects.0.hourly_rate', null)
            ->where('projects.1.name', 'Website redesign')
            ->where('projects.1.code', 'WEB')
            ->where('projects.1.client_id', $this->client->id)
            ->where('projects.1.is_billable', true)
            ->where('projects.1.hourly_rate', '95.00'));

    mcpTimeTool(ListProjectsTool::class, ['search' => 'acme'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->has('projects', 1)->where('projects.0.name', 'Website redesign'));

    mcpTimeTool(ListProjectsTool::class, ['client_id' => $logo->client_id])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->has('projects', 1)->where('projects.0.name', 'Logo'));

    mcpTimeTool(ListProjectsTool::class, ['include_inactive' => true])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->has('projects', 3));
});

it('lists time entries in a period with filters and totals', function (): void {
    $other = Project::factory()->create(['name' => 'Other']);
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-01', 'hours' => '2.00', 'notes' => 'Homepage']);
    TimeEntry::factory()->for($this->user)->for($this->project)->billed()->create(['spent_on' => '2026-09-02', 'hours' => '1.00']);
    TimeEntry::factory()->for($this->user)->for($other)->create(['spent_on' => '2026-09-03', 'hours' => '0.50', 'is_billable' => false]);
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-08-31', 'hours' => '4.00']);
    TimeEntry::factory()->for($this->project)->create(['spent_on' => '2026-09-01', 'hours' => '8.00']);

    mcpTimeTool(ListTimeEntriesTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('from', '2026-09-01')
            ->where('to', '2026-09-09')
            ->where('entry_count', 3)
            ->where('total_hours', '3.50')
            ->where('billable_hours', '3.00')
            ->where('truncated', false)
            ->has('entries', 3)
            ->where('entries.0.spent_on', '2026-09-03')
            ->where('entries.2.spent_on', '2026-09-01')
            ->where('entries.2.project', 'Website redesign')
            ->where('entries.2.client', 'Acme Corporation')
            ->where('entries.2.notes', 'Homepage')
            ->where('entries.2.hours', '2.00')
            ->where('entries.2.is_billed', false)
            ->missing('entries.2.task_id'));

    mcpTimeTool(ListTimeEntriesTool::class, ['from' => '2026-08-01', 'to' => '2026-09-30', 'project_id' => $this->project->id, 'unbilled_only' => true])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('entry_count', 2)
            ->where('total_hours', '6.00')
            ->etc());

    mcpTimeTool(ListTimeEntriesTool::class, ['client_id' => $other->client_id])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('entry_count', 1)->etc());

    mcpTimeTool(ListTimeEntriesTool::class, ['limit' => 1])
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('entry_count', 3)->where('truncated', true)->has('entries', 1)->etc());
});

it('rejects an invalid period', function (): void {
    mcpTimeTool(ListTimeEntriesTool::class, ['from' => '2026-09-10', 'to' => '2026-09-01'])
        ->assertHasErrors(['to']);
});

it('returns the week timesheet around a date', function (): void {
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-08', 'hours' => '3.00']);
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-09', 'hours' => '1.25']);
    TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-06', 'hours' => '9.00']);

    mcpTimeTool(GetTimesheetTool::class, ['date' => '2026-09-08'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('selected_date', '2026-09-08')
            ->where('week_start', '2026-09-07')
            ->where('week_end', '2026-09-13')
            ->where('week_total', '4.25')
            ->where('day_total', '3.00')
            ->has('days', 7)
            ->where('days.0.date', '2026-09-07')
            ->where('days.1.total_hours', '3.00')
            ->where('days.2.is_today', true)
            ->has('entries', 1)
            ->where('entries.0.project', 'Website redesign'));
});

it('logs time on a project with the inherited rate', function (): void {
    mcpTimeTool(LogTimeTool::class, [
        'project_id' => $this->project->id,
        'hours' => 1.5,
        'notes' => 'Built the homepage',
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('message', 'Time entry logged.')
            ->where('entry.spent_on', '2026-09-09')
            ->where('entry.hours', '1.50')
            ->where('entry.project', 'Website redesign')
            ->where('entry.client', 'Acme Corporation')
            ->where('entry.notes', 'Built the homepage')
            ->where('entry.is_billable', true)
            ->where('entry.hourly_rate', '95.00')
            ->where('entry.is_running', false)
            ->etc());

    $entry = TimeEntry::query()->firstOrFail();
    expect($entry->user_id)->toBe($this->user->id)
        ->and($entry->project_id)->toBe($this->project->id);
});

it('logs time on a date, non-billable, and can start a timer', function (): void {
    mcpTimeTool(LogTimeTool::class, ['project_id' => $this->project->id, 'spent_on' => '2026-09-01', 'hours' => '0.25', 'is_billable' => false])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('entry.spent_on', '2026-09-01')
            ->where('entry.hours', '0.25')
            ->where('entry.is_billable', false)
            ->etc());

    mcpTimeTool(LogTimeTool::class, ['project_id' => $this->project->id, 'start_timer' => true])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('message', 'Timer started.')
            ->where('entry.is_running', true)
            ->where('entry.hours', '0.00')
            ->etc());

    expect(TimeEntry::query()->where('is_running', true)->count())->toBe(1);
});

it('explains an unknown project and invalid hours', function (): void {
    mcpTimeTool(LogTimeTool::class, ['project_id' => 999, 'hours' => 1])
        ->assertHasErrors(['No project with id 999']);

    mcpTimeTool(LogTimeTool::class, ['project_id' => $this->project->id, 'hours' => 30])
        ->assertHasErrors(['hours']);

    mcpTimeTool(LogTimeTool::class, ['project_id' => $this->project->id])
        ->assertHasErrors(['hours']);

    expect(TimeEntry::query()->count())->toBe(0);
});

it('updates only the given fields of an entry', function (): void {
    $entry = TimeEntry::factory()->for($this->user)->for($this->project)->create(['spent_on' => '2026-09-01', 'hours' => '2.00', 'notes' => 'Old', 'hourly_rate' => '95.00']);
    $other = Project::factory()->for($this->client)->create(['name' => 'Other', 'hourly_rate' => '150.00']);

    mcpTimeTool(UpdateTimeEntryTool::class, ['time_entry_id' => $entry->id, 'hours' => 2.75, 'notes' => 'New'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('entry.hours', '2.75')
            ->where('entry.notes', 'New')
            ->where('entry.spent_on', '2026-09-01')
            ->where('entry.project', 'Website redesign')
            ->where('entry.hourly_rate', '95.00')
            ->etc());

    mcpTimeTool(UpdateTimeEntryTool::class, ['time_entry_id' => $entry->id, 'project_id' => $other->id, 'spent_on' => '2026-09-02'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('entry.project', 'Other')
            ->where('entry.hourly_rate', '150.00')
            ->where('entry.spent_on', '2026-09-02')
            ->where('entry.hours', '2.75')
            ->etc());

    mcpTimeTool(UpdateTimeEntryTool::class, ['time_entry_id' => $entry->id, 'project_id' => 999])
        ->assertHasErrors(['No project with id 999']);
});

it('refuses to change, delete or time a billed entry', function (): void {
    $entry = TimeEntry::factory()->for($this->user)->for($this->project)->billed()->create(['hours' => '2.00']);

    mcpTimeTool(UpdateTimeEntryTool::class, ['time_entry_id' => $entry->id, 'hours' => 3])
        ->assertHasErrors(["Time entry #{$entry->id} has been billed"]);
    mcpTimeTool(DeleteTimeEntryTool::class, ['time_entry_id' => $entry->id])
        ->assertHasErrors(['has been billed']);
    mcpTimeTool(StartTimerTool::class, ['time_entry_id' => $entry->id])
        ->assertHasErrors(['has been billed']);

    expect($entry->fresh()?->hours)->toBe('2.00');
});

it('deletes an entry and refuses entries of other users', function (): void {
    $entry = TimeEntry::factory()->for($this->user)->for($this->project)->create();
    $someoneElse = TimeEntry::factory()->for($this->project)->create();

    mcpTimeTool(DeleteTimeEntryTool::class, ['time_entry_id' => $entry->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('deleted_entry.id', $entry->id)->etc());

    mcpTimeTool(DeleteTimeEntryTool::class, ['time_entry_id' => $someoneElse->id])
        ->assertHasErrors(["No time entry with id {$someoneElse->id} exists for you"]);

    expect(TimeEntry::query()->find($entry->id))->toBeNull()
        ->and(TimeEntry::query()->find($someoneElse->id))->not->toBeNull();
});

it('starts a timer, stopping the other running one', function (): void {
    $running = TimeEntry::factory()->for($this->user)->for($this->project)->create(['hours' => '1.00', 'is_running' => true, 'timer_started_at' => Carbon::parse('2026-09-09 09:30:00')]);
    $next = TimeEntry::factory()->for($this->user)->for($this->project)->create(['hours' => '0.25']);

    mcpTimeTool(StartTimerTool::class, ['time_entry_id' => $next->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('message', 'Timer started.')
            ->where('entry.id', $next->id)
            ->where('entry.is_running', true)
            ->etc());

    expect($running->fresh()?->is_running)->toBeFalse()
        ->and($running->fresh()?->hours)->toBe('1.50');
});

it('reports and stops the running timer', function (): void {
    mcpTimeTool(GetRunningTimerTool::class)
        ->assertOk()
        ->assertStructuredContent(['running' => false, 'entry' => null]);

    mcpTimeTool(StopTimerTool::class)->assertHasErrors(['No timer is running.']);

    $entry = TimeEntry::factory()->for($this->user)->for($this->project)->create(['hours' => '1.00', 'is_running' => true, 'timer_started_at' => Carbon::parse('2026-09-09 09:00:00')]);

    mcpTimeTool(GetRunningTimerTool::class)
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('running', true)
            ->where('elapsed_minutes', 60)
            ->where('entry.id', $entry->id)
            ->where('entry.hours', '2.00')
            ->etc());

    mcpTimeTool(StopTimerTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('message', 'Timer stopped at 2.00 hours.')
            ->where('entry.is_running', false)
            ->where('entry.hours', '2.00')
            ->etc());

    mcpTimeTool(StopTimerTool::class, ['time_entry_id' => $entry->id])
        ->assertHasErrors(["Time entry #{$entry->id} has no running timer."]);
});
