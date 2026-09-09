<?php

declare(strict_types=1);

use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('starts a timer and stops the other running one, keeping its time', function (): void {
    $this->travelTo(Carbon::parse('2026-09-09 10:00:00'));
    $running = TimeEntry::factory()->for($this->user)->create([
        'hours' => '1.00',
        'is_running' => true,
        'timer_started_at' => Carbon::parse('2026-09-09 09:30:00'),
    ]);
    $next = TimeEntry::factory()->for($this->user)->create(['hours' => '0.25']);
    $someoneElse = TimeEntry::factory()->running()->create();

    $this->post(route('time-entries.start', $next))->assertRedirect();

    expect($next->fresh()->is_running)->toBeTrue()
        ->and($next->fresh()->timer_started_at?->toDateTimeString())->toBe('2026-09-09 10:00:00')
        ->and($next->fresh()->hours)->toBe('0.25')
        ->and($running->fresh()->is_running)->toBeFalse()
        ->and($running->fresh()->timer_started_at)->toBeNull()
        ->and($running->fresh()->hours)->toBe('1.50')
        ->and($someoneElse->fresh()->is_running)->toBeTrue();
});

it('stops a timer and folds the elapsed time into the hours', function (): void {
    $this->travelTo(Carbon::parse('2026-09-09 09:00:00'));
    $entry = TimeEntry::factory()->for($this->user)->create(['hours' => '2.00']);

    $this->post(route('time-entries.start', $entry))->assertRedirect();

    $this->travelTo(Carbon::parse('2026-09-09 09:20:00'));

    $this->post(route('time-entries.stop', $entry))->assertRedirect();

    $entry->refresh();
    expect($entry->is_running)->toBeFalse()
        ->and($entry->timer_started_at)->toBeNull()
        ->and($entry->hours)->toBe('2.33');
});

it('refuses to time a billed entry', function (): void {
    $entry = TimeEntry::factory()->for($this->user)->billed()->create();

    $this->from(route('time-entries.index'))
        ->post(route('time-entries.start', $entry))
        ->assertRedirect(route('time-entries.index'))
        ->assertSessionHas('toast.type', 'error');

    expect($entry->fresh()->is_running)->toBeFalse();
});

it('shares the running timer with every page', function (): void {
    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('runningTimer', null));

    $entry = TimeEntry::factory()->for($this->user)->running()->create();
    TimeEntry::factory()->running()->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('runningTimer.id', $entry->id)
            ->where('runningTimer.is_running', true)
            ->whereNot('runningTimer.timer_started_at', null)
            ->where('runningTimer.project_name', $entry->project->name));
});
