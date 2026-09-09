<?php

declare(strict_types=1);

use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;

/**
 * Logging, editing and deleting hours through Chromium: the dedicated form
 * page and the "Log time" card on the timesheet share one form component.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('logs time from the entry form', function (): void {
    $project = Project::factory()->create(['name' => 'Website']);

    $page = visit('/time-entries/create?date=2026-09-07');

    $page->assertSee('New time entry');
    $page->click('[data-field="project_id"] button[role="combobox"]');
    browserWaitUntilSee($page, 'Website');
    $page->click('[role="option"]:has-text("Website")');
    $page->fill('[data-field="hours"] input', '1.5');
    $page->fill('[data-field="notes"] textarea', 'Refactoring');
    $page->press('Save');

    browserWaitUntilSee($page, 'Time entry saved.');

    $entry = TimeEntry::query()->sole();

    expect($entry->project_id)->toBe($project->id)
        ->and($entry->user_id)->toBe($this->user->id)
        ->and($entry->spent_on->toDateString())->toBe('2026-09-07')
        ->and($entry->hours)->toBe('1.50')
        ->and($entry->notes)->toBe('Refactoring')
        ->and($entry->hourly_rate)->toBe('95.00');

    // The form hands over to the week grid, which lists the project row.
    $page->assertPathIs('/time-entries')
        ->assertQueryStringHas('date', '2026-09-07')
        ->assertSee('Website')
        ->assertSee('1,50')
        ->assertNoJavaScriptErrors();
});

it('logs time from the card on the timesheet', function (): void {
    $project = Project::factory()->create(['name' => 'Website']);

    $page = visit('/time-entries?date=2026-09-07&view=day');

    // With one active project the card has it preselected.
    $page->assertSee('Nothing logged yet');
    $page->fill('[data-field="hours"] input', '2');
    $page->fill('[data-field="notes"] textarea', 'Code review');
    $page->press('Save');

    browserWaitUntilSee($page, 'Time entry saved.');
    browserWaitUntilSee($page, 'Code review');

    $entry = TimeEntry::query()->sole();

    expect($entry->project_id)->toBe($project->id)
        ->and($entry->spent_on->toDateString())->toBe('2026-09-07')
        ->and($entry->hours)->toBe('2.00');

    $page->assertDontSee('Nothing logged yet')->assertNoJavaScriptErrors();
});

it('edits a time entry', function (): void {
    $project = Project::factory()->create();
    $entry = TimeEntry::factory()->for($this->user)->for($project)->create([
        'spent_on' => '2026-09-07',
        'hours' => '1.00',
        'notes' => 'Old note',
    ]);

    $page = visit("/time-entries/{$entry->id}/edit");

    $page->assertSee('Edit time entry');
    $page->fill('[data-field="hours"] input', '2.5');
    $page->fill('[data-field="notes"] textarea', 'New note');
    $page->click('[data-field="is_billable"] button[role="switch"]');
    $page->press('Save');

    browserWaitUntilSee($page, 'Time entry saved.');

    $entry->refresh();

    expect($entry->hours)->toBe('2.50')
        ->and($entry->notes)->toBe('New note')
        ->and($entry->is_billable)->toBeFalse();

    $page->assertNoJavaScriptErrors();
});

it('keeps a billed entry read-only', function (): void {
    $project = Project::factory()->create();
    $entry = TimeEntry::factory()->billed()->for($this->user)->for($project)->create();

    $page = visit("/time-entries/{$entry->id}/edit");

    $page->assertSee('This entry has been billed and can no longer be changed.')
        ->assertButtonDisabled('Save')
        ->assertButtonDisabled('Delete')
        ->assertDisabled('[data-field="hours"] input');
});

it('deletes a time entry after confirming', function (): void {
    $project = Project::factory()->create();
    $entry = TimeEntry::factory()->for($this->user)->for($project)->create(['spent_on' => '2026-09-07']);

    $page = visit("/time-entries/{$entry->id}/edit");

    $page->click('Delete');
    browserConfirmDelete($page, 'Delete this time entry?');

    browserWaitUntilSee($page, 'Time entry deleted.');
    $page->assertPathIs('/time-entries')->assertQueryStringHas('date', '2026-09-07');

    expect(TimeEntry::query()->find($entry->id))->toBeNull();
});
