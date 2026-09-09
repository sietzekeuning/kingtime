<?php

declare(strict_types=1);

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
            ->where('timesheet.entries.0.hours', '1.50'));
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
