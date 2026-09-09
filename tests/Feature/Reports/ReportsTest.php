<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Reports\Actions\BuildReportsAction;
use App\Domain\Reports\Data\ReportsData;
use App\Domain\Reports\Data\ReportsFilterData;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;

/*
 * Frozen on Wednesday 9 September 2026 (ISO week 37). The default month
 * report then runs Oct 2025 to Sep 2026 and is compared with Oct 2024 to
 * Sep 2025; the default week report runs week 12 to week 37 of 2026.
 */
beforeEach(function (): void {
    Carbon::setTestNow('2026-09-09 10:00:00');
    $this->actingAs(User::factory()->create());
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function reportsUser(): User
{
    /** @var User $user */
    $user = auth()->user();

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function reportsEntry(Project $project, string $spentOn, string $hours, array $attributes = []): TimeEntry
{
    return TimeEntry::factory()->for(reportsUser())->for($project)->create([
        'spent_on' => $spentOn,
        'hours' => $hours,
        'hourly_rate' => '100.00',
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $filter
 */
function reportsBuild(array $filter = []): ReportsData
{
    return app(BuildReportsAction::class)->handle(reportsUser(), ReportsFilterData::validateAndCreate($filter));
}

it('redirects guests to the login page', function (): void {
    auth()->logout();

    $this->get(route('reports.index'))->assertRedirect(route('login'));
});

it('buckets the last twelve months by default with totals and deltas against the year before', function (): void {
    $project = Project::factory()->create();

    reportsEntry($project, '2026-09-09', '4.00');
    reportsEntry($project, '2026-09-01', '2.50', ['is_billable' => false]);
    reportsEntry($project, '2026-08-15', '3.00', ['is_billed' => true]);
    reportsEntry($project, '2025-10-01', '1.00');
    reportsEntry($project, '2025-09-30', '5.00');
    reportsEntry($project, '2025-08-01', '3.00');
    reportsEntry($project, '2024-09-30', '7.00');

    reportsEntry($project, '2026-09-09', '9.00')->delete();
    TimeEntry::factory()->for($project)->create(['spent_on' => '2026-09-09', 'hours' => '10.00']);
    reportsEntry($project, '2026-09-09', '1.00', ['is_running' => true, 'timer_started_at' => now()->subMinutes(30)]);

    $report = reportsBuild();

    expect($report->granularity)->toBe('month')
        ->and($report->from)->toBe('2025-10-01')
        ->and($report->to)->toBe('2026-09-30')
        ->and($report->previous_from)->toBe('2024-10-01')
        ->and($report->previous_to)->toBe('2025-09-30')
        ->and($report->earliest_entry_on)->toBe('2024-09-30')
        ->and($report->buckets)->toHaveCount(12)
        ->and($report->buckets[0]->period)->toBe('2025-10')
        ->and($report->buckets[0]->label)->toBe('Oct 2025')
        ->and($report->buckets[0]->starts_on)->toBe('2025-10-01')
        ->and($report->buckets[0]->ends_on)->toBe('2025-10-31')
        ->and($report->buckets[0]->hours)->toBe('1.00')
        ->and($report->buckets[5]->label)->toBe('Mar 2026')
        ->and($report->buckets[5]->hours)->toBe('0.00')
        ->and($report->buckets[5]->entry_count)->toBe(0)
        ->and($report->buckets[10]->label)->toBe('Aug 2026')
        ->and($report->buckets[10]->earned)->toBe('300.00')
        ->and($report->buckets[10]->invoiced)->toBe('300.00')
        ->and($report->buckets[11]->label)->toBe('Sep 2026')
        ->and($report->buckets[11]->hours)->toBe('7.50')
        ->and($report->buckets[11]->billable_hours)->toBe('5.00')
        ->and($report->buckets[11]->earned)->toBe('500.00')
        ->and($report->buckets[11]->invoiced)->toBe('0.00')
        ->and($report->buckets[11]->entry_count)->toBe(3)
        ->and($report->totals->hours)->toBe('11.50')
        ->and($report->totals->billable_hours)->toBe('9.00')
        ->and($report->totals->earned)->toBe('900.00')
        ->and($report->totals->invoiced)->toBe('300.00')
        ->and($report->totals->entry_count)->toBe(5)
        ->and($report->totals->previous_hours)->toBe('8.00')
        ->and($report->totals->previous_earned)->toBe('800.00')
        ->and($report->totals->hours_delta_percent)->toBe(43.8)
        ->and($report->totals->earned_delta_percent)->toBe(12.5)
        ->and($report->totals->invoiced_delta_percent)->toBeNull();
});

it('buckets ISO weeks from Monday and labels them with the ISO year', function (): void {
    $project = Project::factory()->create();

    reportsEntry($project, '2026-09-06', '2.00');
    reportsEntry($project, '2026-09-07', '3.00');
    reportsEntry($project, '2026-08-30', '1.00');
    reportsEntry($project, '2027-01-01', '4.00');

    $twoWeeks = reportsBuild(['granularity' => 'week', 'from' => '2026-08-31', 'to' => '2026-09-13']);

    expect($twoWeeks->previous_from)->toBe('2026-08-17')
        ->and($twoWeeks->previous_to)->toBe('2026-08-30')
        ->and($twoWeeks->buckets)->toHaveCount(2)
        ->and($twoWeeks->buckets[0]->period)->toBe('2026-08-31')
        ->and($twoWeeks->buckets[0]->label)->toBe('Week 36 · 2026')
        ->and($twoWeeks->buckets[0]->ends_on)->toBe('2026-09-06')
        ->and($twoWeeks->buckets[0]->hours)->toBe('2.00')
        ->and($twoWeeks->buckets[1]->label)->toBe('Week 37 · 2026')
        ->and($twoWeeks->buckets[1]->hours)->toBe('3.00')
        ->and($twoWeeks->totals->hours)->toBe('5.00')
        ->and($twoWeeks->totals->previous_hours)->toBe('1.00')
        ->and($twoWeeks->totals->hours_delta_percent)->toBe(400.0);

    $yearBoundary = reportsBuild(['granularity' => 'week', 'from' => '2026-12-28', 'to' => '2027-01-03']);

    expect($yearBoundary->buckets)->toHaveCount(1)
        ->and($yearBoundary->buckets[0]->label)->toBe('Week 53 · 2026')
        ->and($yearBoundary->buckets[0]->hours)->toBe('4.00');

    $default = reportsBuild(['granularity' => 'week']);

    expect($default->from)->toBe('2026-03-16')
        ->and($default->to)->toBe('2026-09-13')
        ->and($default->buckets)->toHaveCount(26)
        ->and($default->buckets[0]->label)->toBe('Week 12 · 2026')
        ->and($default->buckets[25]->label)->toBe('Week 37 · 2026');
});

it('buckets every year since the first entry by default', function (): void {
    $project = Project::factory()->create();

    reportsEntry($project, '2024-03-01', '2.00');
    reportsEntry($project, '2026-09-09', '3.00');

    $report = reportsBuild(['granularity' => 'year']);

    expect($report->from)->toBe('2024-01-01')
        ->and($report->to)->toBe('2026-12-31')
        ->and($report->previous_from)->toBe('2020-12-31')
        ->and($report->previous_to)->toBe('2023-12-31')
        ->and($report->buckets)->toHaveCount(3)
        ->and($report->buckets[0]->period)->toBe('2024')
        ->and($report->buckets[0]->label)->toBe('2024')
        ->and($report->buckets[0]->hours)->toBe('2.00')
        ->and($report->buckets[1]->hours)->toBe('0.00')
        ->and($report->buckets[2]->label)->toBe('2026')
        ->and($report->buckets[2]->hours)->toBe('3.00')
        ->and($report->totals->hours)->toBe('5.00')
        ->and($report->totals->hours_delta_percent)->toBeNull();
});

it('falls back to the current year without entries', function (): void {
    $report = reportsBuild(['granularity' => 'year']);

    expect($report->earliest_entry_on)->toBeNull()
        ->and($report->from)->toBe('2026-01-01')
        ->and($report->buckets)->toHaveCount(1)
        ->and($report->buckets[0]->label)->toBe('2026')
        ->and($report->totals->hours)->toBe('0.00')
        ->and($report->clients)->toBeEmpty()
        ->and($report->projects)->toBeEmpty();
});

it('narrows the report to a client or a project', function (): void {
    $acme = Client::factory()->create(['name' => 'Acme']);
    $globex = Client::factory()->create(['name' => 'Globex']);
    $alpha = Project::factory()->for($acme)->create(['name' => 'Alpha']);
    $beta = Project::factory()->for($acme)->create(['name' => 'Beta']);
    $gamma = Project::factory()->for($globex)->create(['name' => 'Gamma']);

    reportsEntry($alpha, '2026-09-01', '4.00');
    reportsEntry($beta, '2026-09-02', '2.00');
    reportsEntry($gamma, '2026-09-03', '6.00');
    reportsEntry($gamma, '2025-09-03', '1.00');

    $byClient = reportsBuild(['client_id' => $acme->id]);

    expect($byClient->totals->hours)->toBe('6.00')
        ->and($byClient->totals->earned)->toBe('600.00')
        ->and($byClient->clients)->toHaveCount(1)
        ->and($byClient->clients[0]->name)->toBe('Acme')
        ->and($byClient->clients[0]->share_percent)->toBe(100.0)
        ->and($byClient->projects->pluck('name')->all())->toBe(['Alpha', 'Beta'])
        ->and($byClient->earliest_entry_on)->toBe('2026-09-01');

    $byProject = reportsBuild(['project_id' => $gamma->id]);

    expect($byProject->totals->hours)->toBe('6.00')
        ->and($byProject->totals->previous_hours)->toBe('1.00')
        ->and($byProject->buckets[11]->entry_count)->toBe(1)
        ->and($byProject->projects)->toHaveCount(1)
        ->and($byProject->projects[0]->name)->toBe('Gamma');
});

it('leaves non-billable hours out with billable_only', function (): void {
    $project = Project::factory()->create();

    reportsEntry($project, '2026-09-01', '4.00');
    reportsEntry($project, '2026-09-02', '2.00', ['is_billable' => false]);
    reportsEntry($project, '2025-09-02', '3.00', ['is_billable' => false]);

    $all = reportsBuild();
    $billable = reportsBuild(['billable_only' => '1']);

    expect($all->totals->hours)->toBe('6.00')
        ->and($all->totals->entry_count)->toBe(2)
        ->and($all->totals->previous_hours)->toBe('3.00')
        ->and($billable->totals->hours)->toBe('4.00')
        ->and($billable->totals->billable_hours)->toBe('4.00')
        ->and($billable->totals->entry_count)->toBe(1)
        ->and($billable->totals->previous_hours)->toBe('0.00')
        ->and($billable->clients[0]->hours)->toBe('4.00');
});

it('breaks the range down per client and per project with their share of the hours', function (): void {
    $acme = Client::factory()->create(['name' => 'Acme']);
    $globex = Client::factory()->create(['name' => 'Globex']);
    $alpha = Project::factory()->for($acme)->create(['name' => 'Alpha', 'color' => '#F97316']);
    $beta = Project::factory()->for($acme)->create(['name' => 'Beta']);
    $gamma = Project::factory()->for($globex)->create(['name' => 'Gamma']);

    reportsEntry($alpha, '2026-09-01', '3.00');
    reportsEntry($alpha, '2026-09-02', '1.00', ['is_billable' => false]);
    reportsEntry($beta, '2026-09-03', '2.00');
    reportsEntry($gamma, '2026-09-04', '2.00');
    reportsEntry($gamma, '2024-09-04', '50.00');

    $report = reportsBuild();

    expect($report->clients)->toHaveCount(2)
        ->and($report->clients[0]->id)->toBe($acme->id)
        ->and($report->clients[0]->name)->toBe('Acme')
        ->and($report->clients[0]->client_name)->toBeNull()
        ->and($report->clients[0]->hours)->toBe('6.00')
        ->and($report->clients[0]->billable_hours)->toBe('5.00')
        ->and($report->clients[0]->earned)->toBe('500.00')
        ->and($report->clients[0]->entry_count)->toBe(3)
        ->and($report->clients[0]->share_percent)->toBe(75.0)
        ->and($report->clients[1]->name)->toBe('Globex')
        ->and($report->clients[1]->share_percent)->toBe(25.0)
        ->and($report->projects)->toHaveCount(3)
        ->and($report->projects[0]->name)->toBe('Alpha')
        ->and($report->projects[0]->client_name)->toBe('Acme')
        ->and($report->projects[0]->color)->toBe('#F97316')
        ->and($report->projects[0]->share_percent)->toBe(50.0)
        ->and($report->projects[1]->name)->toBe('Beta')
        ->and($report->projects[1]->share_percent)->toBe(25.0)
        ->and($report->projects[2]->name)->toBe('Gamma');
});

it('lists at most fifteen projects', function (): void {
    $client = Client::factory()->create();

    foreach (range(1, 17) as $index) {
        reportsEntry(Project::factory()->for($client)->create(['name' => sprintf('Project %02d', $index)]), '2026-09-01', (string) $index);
    }

    $report = reportsBuild();

    expect($report->projects)->toHaveCount(BuildReportsAction::TOP_PROJECTS)
        ->and($report->projects[0]->name)->toBe('Project 17')
        ->and($report->projects[14]->name)->toBe('Project 03')
        ->and($report->clients[0]->share_percent)->toBe(100.0);
});

it('rejects an invalid filter with errors', function (): void {
    $this->from(route('dashboard'))
        ->get(route('reports.index', ['granularity' => 'day', 'from' => '2026-09-10', 'to' => '2026-09-01', 'client_id' => 999]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors(['granularity', 'to', 'client_id']);
});

it('renders the reports page with the filter and the select options', function (): void {
    $client = Client::factory()->create(['name' => 'Acme']);
    $project = Project::factory()->for($client)->create(['name' => 'Alpha']);
    reportsEntry($project, '2026-09-09', '2.00');

    $this->get(route('reports.index', ['client_id' => $client->id, 'billable_only' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/ReportsIndex')
            ->where('filter.granularity', 'month')
            ->where('filter.client_id', $client->id)
            ->where('filter.billable_only', true)
            ->where('reports.from', '2025-10-01')
            ->has('reports.buckets', 12)
            ->where('reports.buckets.11.hours', '2.00')
            ->where('reports.totals.earned', '200.00')
            ->has('reports.clients', 1)
            ->has('reports.projects', 1)
            ->where('clients.0.name', 'Acme')
            ->where('projects.0.client_id', $client->id));
});
