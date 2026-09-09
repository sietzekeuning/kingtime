<?php

declare(strict_types=1);

use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('builds the week around the selected day with per-day and week totals', function (): void {
    $this->travelTo(Carbon::parse('2026-09-09 12:00:00'));

    TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-07', 'hours' => '2.00']);
    TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-09', 'hours' => '1.50']);
    TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-09', 'hours' => '0.50']);
    TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-13', 'hours' => '1.00']);
    TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-14', 'hours' => '8.00']);
    TimeEntry::factory()->create(['spent_on' => '2026-09-09', 'hours' => '4.00']);

    $this->get(route('time-entries.index', ['date' => '2026-09-09']))
        ->assertInertia(fn ($page) => $page
            ->where('timesheet.selected_date', '2026-09-09')
            ->where('timesheet.week_start', '2026-09-07')
            ->where('timesheet.week_end', '2026-09-13')
            ->where('timesheet.week_total', '5.00')
            ->where('timesheet.day_total', '2.00')
            ->has('timesheet.days', 7)
            ->where('timesheet.days.0.date', '2026-09-07')
            ->where('timesheet.days.0.weekday', 'Mon')
            ->where('timesheet.days.0.total_hours', '2.00')
            ->where('timesheet.days.2.is_today', true)
            ->where('timesheet.days.2.entries_count', 2)
            ->where('timesheet.days.6.date', '2026-09-13')
            ->where('timesheet.days.6.total_hours', '1.00')
            ->has('timesheet.entries', 2)
            ->where('timesheet.entries.0.hours', '1.50')
            ->where('timesheet.days.5.is_weekend', true)
            ->where('timesheet.days.6.is_future', true));
});

it('builds one grid row per project with a cell per day and remembers last week', function (): void {
    $this->travelTo(Carbon::parse('2026-09-09 12:00:00'));

    $alpha = Project::factory()->create(['name' => 'Alpha']);
    $beta = Project::factory()->create(['name' => 'beta']);
    $lastWeek = Project::factory()->create(['name' => 'Gamma']);

    TimeEntry::factory()->for($this->user)->for($beta)->create(['spent_on' => '2026-09-07', 'hours' => '2.00', 'notes' => 'Kickoff']);
    TimeEntry::factory()->for($this->user)->for($alpha)->create(['spent_on' => '2026-09-08', 'hours' => '1.00', 'notes' => null]);
    TimeEntry::factory()->for($this->user)->for($alpha)->create(['spent_on' => '2026-09-08', 'hours' => '0.50', 'notes' => null]);
    TimeEntry::factory()->for($this->user)->for($alpha)->billed()->create(['spent_on' => '2026-09-09', 'hours' => '3.00']);
    TimeEntry::factory()->for($this->user)->for($lastWeek)->create(['spent_on' => '2026-09-04', 'hours' => '4.00']);
    TimeEntry::factory()->for($beta)->create(['spent_on' => '2026-09-07', 'hours' => '9.00']);

    $this->get(route('time-entries.index', ['date' => '2026-09-09']))
        ->assertInertia(fn ($page) => $page
            ->has('timesheet.rows', 2)
            ->where('timesheet.rows.0.project_name', 'Alpha')
            ->where('timesheet.rows.0.client_name', $alpha->client->name)
            ->where('timesheet.rows.0.total_hours', '4.50')
            ->where('timesheet.rows.0.is_locked', false)
            ->has('timesheet.rows.0.cells', 7)
            ->where('timesheet.rows.0.cells.0.hours', '0.00')
            ->where('timesheet.rows.0.cells.0.entry_id', null)
            ->where('timesheet.rows.0.cells.1.hours', '1.50')
            ->where('timesheet.rows.0.cells.1.entries_count', 2)
            ->where('timesheet.rows.0.cells.1.entry_id', null)
            ->where('timesheet.rows.0.cells.2.hours', '3.00')
            ->where('timesheet.rows.0.cells.2.is_locked', true)
            ->where('timesheet.rows.1.project_name', 'beta')
            ->where('timesheet.rows.1.cells.0.entry_id', fn (int $id) => $id > 0)
            ->where('timesheet.rows.1.cells.0.notes', 'Kickoff')
            ->where('timesheet.previous_week_project_ids', [$lastWeek->id]));
});

it('builds the month overview with the working days that still miss hours', function (): void {
    $this->travelTo(Carbon::parse('2026-09-09 12:00:00'));

    TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-01', 'hours' => '8.00']);
    TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-02', 'hours' => '2.00']);
    TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-05', 'hours' => '1.00']);
    TimeEntry::factory()->for($this->user)->create(['spent_on' => '2026-09-30', 'hours' => '1.00']);
    TimeEntry::factory()->create(['spent_on' => '2026-09-03', 'hours' => '4.00']);

    // Working days up to today: 1, 2, 3, 4, 7, 8, 9; booked: 1 and 2.
    $this->get(route('time-entries.index', ['date' => '2026-09-09']))
        ->assertInertia(fn ($page) => $page
            ->where('month.month_start', '2026-09-01')
            ->where('month.month_end', '2026-09-30')
            ->where('month.total_hours', '12.00')
            ->where('month.working_days', 7)
            ->where('month.booked_days', 2)
            ->where('month.missing_days', 5)
            ->has('month.days', 30)
            ->where('month.days.0.total_hours', '8.00')
            ->where('month.days.2.entries_count', 0)
            ->where('month.days.4.is_weekend', true)
            ->where('month.days.8.is_today', true)
            ->where('month.days.9.is_future', true));
});

it('defaults to today and counts a running timer live', function (): void {
    $this->travelTo(Carbon::parse('2026-09-11 10:00:00'));

    TimeEntry::factory()->for($this->user)->create([
        'spent_on' => '2026-09-11',
        'hours' => '1.00',
        'is_running' => true,
        'timer_started_at' => Carbon::parse('2026-09-11 09:30:00'),
    ]);

    $this->get(route('time-entries.index'))
        ->assertInertia(fn ($page) => $page
            ->where('timesheet.selected_date', '2026-09-11')
            ->where('timesheet.week_start', '2026-09-07')
            ->where('timesheet.day_total', '1.50')
            ->where('timesheet.entries.0.hours', '1.50')
            ->where('timesheet.entries.0.is_running', true));
});
