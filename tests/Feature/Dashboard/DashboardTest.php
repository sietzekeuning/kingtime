<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Dashboard\Actions\BuildDashboardAction;
use App\Domain\Dashboard\Data\DashboardData;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/*
 * Frozen on Wednesday 9 September 2026: this week runs 7-13 Sep, last week
 * 31 Aug-6 Sep, and the month has had 7 working days (1-4 and 7-9 Sep).
 */
beforeEach(function (): void {
    Carbon::setTestNow('2026-09-09 10:00:00');
    $this->actingAs(User::factory()->create());
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function dashboardUser(): User
{
    /** @var User $user */
    $user = auth()->user();

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function dashboardEntry(Project $project, string $spentOn, string $hours, array $attributes = []): TimeEntry
{
    return TimeEntry::factory()->for(dashboardUser())->for($project)->create([
        'spent_on' => $spentOn,
        'hours' => $hours,
        'hourly_rate' => '100.00',
        ...$attributes,
    ]);
}

function dashboardBuild(string $range = BuildDashboardAction::DEFAULT_RANGE): DashboardData
{
    return app(BuildDashboardAction::class)->handle(dashboardUser(), $range);
}

it('redirects guests to the login page', function (): void {
    auth()->logout();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('sums the four stat tiles and their deltas against the previous period', function (): void {
    $project = Project::factory()->create();

    dashboardEntry($project, '2026-09-09', '4.00');
    dashboardEntry($project, '2026-09-09', '2.50', ['is_billable' => false]);
    dashboardEntry($project, '2026-09-08', '5.00');
    dashboardEntry($project, '2026-09-07', '3.00');
    dashboardEntry($project, '2026-09-04', '6.00');
    dashboardEntry($project, '2026-09-01', '2.00');
    dashboardEntry($project, '2026-08-31', '4.00');
    dashboardEntry($project, '2026-08-15', '8.00');

    TimeEntry::factory()->for($project)->create(['spent_on' => '2026-09-09', 'hours' => '10.00']);
    dashboardEntry($project, '2026-09-09', '9.00')->delete();

    $stats = dashboardBuild()->stats->items;

    expect($stats)->toHaveCount(4)
        ->and($stats[0]->label)->toBe('Hours today')
        ->and($stats[0]->format)->toBe('hours')
        ->and($stats[0]->value)->toBe('6.50')
        ->and($stats[0]->previous_value)->toBe('5.00')
        ->and($stats[0]->delta_percent)->toBe(30.0)
        ->and($stats[0]->current_label)->toBe('Today')
        ->and($stats[0]->previous_label)->toBe('Yesterday')
        ->and($stats[1]->label)->toBe('Hours this week')
        ->and($stats[1]->value)->toBe('14.50')
        ->and($stats[1]->previous_value)->toBe('12.00')
        ->and($stats[1]->delta_percent)->toBe(20.8)
        ->and($stats[2]->label)->toBe('Hours this month')
        ->and($stats[2]->value)->toBe('22.50')
        ->and($stats[2]->previous_value)->toBe('12.00')
        ->and($stats[2]->delta_percent)->toBe(87.5)
        ->and($stats[3]->label)->toBe('Billable this month')
        ->and($stats[3]->format)->toBe('euro')
        ->and($stats[3]->value)->toBe('2000.00')
        ->and($stats[3]->previous_value)->toBe('1200.00')
        ->and($stats[3]->delta_percent)->toBe(66.7);
});

it('leaves the delta empty when the previous period has no hours', function (): void {
    dashboardEntry(Project::factory()->create(), '2026-09-09', '3.00');

    $stats = dashboardBuild()->stats->items;

    expect($stats[0]->value)->toBe('3.00')
        ->and($stats[0]->previous_value)->toBe('0.00')
        ->and($stats[0]->delta_percent)->toBeNull();
});

it('counts a running timer for its elapsed time', function (): void {
    $project = Project::factory()->create(['name' => 'Live project']);

    dashboardEntry($project, '2026-09-09', '1.00', [
        'is_running' => true,
        'timer_started_at' => now()->subMinutes(30),
    ]);

    $dashboard = dashboardBuild();

    expect($dashboard->stats->items[0]->value)->toBe('1.50')
        ->and($dashboard->stats->items[3]->value)->toBe('150.00')
        ->and($dashboard->statistics->running_timer?->project_name)->toBe('Live project')
        ->and($dashboard->statistics->running_timer?->hours)->toBe('1.50')
        ->and($dashboard->top_projects[0]->hours)->toBe('1.50');
});

it('buckets chart hours per week for every range', function (): void {
    $project = Project::factory()->create();

    dashboardEntry($project, '2026-09-09', '6.50');
    dashboardEntry($project, '2026-08-31', '4.00');
    dashboardEntry($project, '2026-06-15', '2.00');
    dashboardEntry($project, '2026-06-14', '1.00');
    dashboardEntry($project, '2025-09-14', '5.00');

    $threeMonths = dashboardBuild('3m')->chart;
    $sixMonths = dashboardBuild('6m')->chart;
    $oneYear = dashboardBuild('1y')->chart;

    expect($threeMonths->range)->toBe('3m')
        ->and($threeMonths->labels)->toHaveCount(13)
        ->and($threeMonths->labels[0])->toBe('Jun 15')
        ->and($threeMonths->labels[12])->toBe('Sep 7')
        ->and($threeMonths->values[0])->toBe(2.0)
        ->and($threeMonths->values[11])->toBe(4.0)
        ->and($threeMonths->values[12])->toBe(6.5)
        ->and(array_sum($threeMonths->values))->toBe(12.5)
        ->and($sixMonths->range)->toBe('6m')
        ->and($sixMonths->labels)->toHaveCount(26)
        ->and($sixMonths->labels[0])->toBe('Mar 16')
        ->and($sixMonths->values[12])->toBe(1.0)
        ->and($sixMonths->values[13])->toBe(2.0)
        ->and(array_sum($sixMonths->values))->toBe(13.5)
        ->and($oneYear->range)->toBe('1y')
        ->and($oneYear->labels)->toHaveCount(52)
        ->and($oneYear->labels[0])->toBe('Sep 15')
        ->and(array_sum($oneYear->values))->toBe(13.5);
});

it('falls back to six months for an unknown range', function (): void {
    expect(dashboardBuild('99y')->chart->range)->toBe('6m');
});

it('ranks the top projects of the month by hours with their share', function (): void {
    $client = Client::factory()->create(['name' => 'Acme']);
    $alpha = Project::factory()->for($client)->create(['name' => 'Alpha', 'color' => '#F97316']);
    $beta = Project::factory()->for($client)->create(['name' => 'Beta']);
    $gamma = Project::factory()->for($client)->create(['name' => 'Gamma']);

    dashboardEntry($alpha, '2026-09-01', '6.00');
    dashboardEntry($alpha, '2026-09-05', '4.00');
    dashboardEntry($beta, '2026-09-08', '5.00');
    dashboardEntry($beta, '2026-08-20', '20.00');
    dashboardEntry($gamma, '2026-09-09', '2.50');

    foreach (range(1, 4) as $index) {
        dashboardEntry(Project::factory()->for($client)->create(['name' => "Filler {$index}"]), '2026-09-02', '0.25');
    }

    $top = dashboardBuild()->top_projects;

    expect($top)->toHaveCount(6)
        ->and($top[0]->name)->toBe('Alpha')
        ->and($top[0]->id)->toBe($alpha->id)
        ->and($top[0]->client_name)->toBe('Acme')
        ->and($top[0]->color)->toBe('#F97316')
        ->and($top[0]->hours)->toBe('10.00')
        ->and($top[0]->percent_of_month)->toBe(54.1)
        ->and($top[1]->name)->toBe('Beta')
        ->and($top[1]->hours)->toBe('5.00')
        ->and($top[2]->name)->toBe('Gamma')
        ->and($top[2]->percent_of_month)->toBe(13.5)
        ->and($top[3]->name)->toBe('Filler 1');
});

it('lists the six most recent entries newest first with their project', function (): void {
    $project = Project::factory()->create(['name' => 'Recent project']);

    foreach (range(1, 8) as $day) {
        dashboardEntry($project, sprintf('2026-09-%02d', $day), '1.00', ['notes' => "Day {$day}"]);
    }

    $recent = dashboardBuild()->recent_entries;

    expect($recent)->toHaveCount(6)
        ->and($recent[0]->spent_on)->toBe('2026-09-08')
        ->and($recent[0]->notes)->toBe('Day 8')
        ->and($recent[0]->project_name)->toBe('Recent project')
        ->and($recent[0]->client_name)->not->toBeNull()
        ->and($recent[5]->spent_on)->toBe('2026-09-03');
});

it('derives the billable ratio and the average per working day', function (): void {
    $project = Project::factory()->create();

    dashboardEntry($project, '2026-09-09', '4.00');
    dashboardEntry($project, '2026-09-09', '2.50', ['is_billable' => false]);
    dashboardEntry($project, '2026-09-01', '16.00');
    dashboardEntry($project, '2026-08-15', '8.00');

    $statistics = dashboardBuild()->statistics;

    expect($statistics->billable_ratio)->toBe(88.9)
        ->and($statistics->billable_ratio_previous)->toBe(100.0)
        ->and($statistics->billable_ratio_delta)->toBe(-11.1)
        ->and($statistics->working_days_this_month)->toBe(7)
        ->and($statistics->average_hours_per_working_day)->toBe('3.21')
        ->and($statistics->running_timer)->toBeNull();
});

it('renders the dashboard with every section', function (): void {
    dashboardEntry(Project::factory()->create(), '2026-09-09', '2.00');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats.items', 4)
            ->where('stats.items.0.value', '2.00')
            ->where('chart.range', '6m')
            ->has('chart.labels', 26)
            ->has('chart.values', 26)
            ->has('statistics.billable_ratio')
            ->has('recent_entries', 1)
            ->has('top_projects', 1));
});

it('reloads only the chart for a partial range visit', function (): void {
    $this->get(route('dashboard'))->assertOk();

    $this->get(route('dashboard', ['range' => '3m']), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'chart',
    ])
        ->assertOk()
        ->assertJsonPath('props.chart.range', '3m')
        ->assertJsonCount(13, 'props.chart.labels')
        ->assertJsonMissingPath('props.stats')
        ->assertJsonMissingPath('props.recent_entries');
});
