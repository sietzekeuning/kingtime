<?php

declare(strict_types=1);

namespace App\Domain\Time\Controllers;

use App\Domain\Client\Data\ClientData;
use App\Domain\Client\Models\Client;
use App\Domain\Time\Actions\BuildMonthOverviewAction;
use App\Domain\Time\Actions\BuildTimesheetAction;
use App\Domain\Time\Actions\DeleteTimeEntryAction;
use App\Domain\Time\Actions\ListProjectOptionsAction;
use App\Domain\Time\Actions\LogTimeAction;
use App\Domain\Time\Actions\StartTimerAction;
use App\Domain\Time\Actions\UpdateTimeEntryAction;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Tables\TimeEntryTable;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeEntryController
{
    public function index(
        Request $request,
        TimeEntryTable $table,
        BuildTimesheetAction $buildTimesheet,
        BuildMonthOverviewAction $buildMonthOverview,
        ListProjectOptionsAction $listProjectOptions,
    ): Response {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        /** @var User $user */
        $user = $request->user();
        $date = $request->filled('date') ? CarbonImmutable::parse($request->string('date')->toString()) : now();

        return Inertia::render('time-entries/TimeEntryList', [
            'timesheet' => fn () => $buildTimesheet->handle($user, $date),
            'month' => fn () => $buildMonthOverview->handle($user, $date),
            'items' => fn () => $table->getData($request)->through(fn (TimeEntry $entry) => TimeEntryData::fromModel($entry)),
            'projects' => fn () => $listProjectOptions->handle(),
            'clients' => fn () => Client::query()->where('is_active', true)->withCount('projects')->orderBy('name')->get()
                ->map(fn (Client $client) => ClientData::fromModel($client)),
        ]);
    }

    public function create(Request $request, ListProjectOptionsAction $listProjectOptions): Response
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return Inertia::render('time-entries/TimeEntryForm', [
            'entry' => TimeEntryData::empty([
                'spent_on' => $request->filled('date') ? $request->string('date')->toString() : now()->toDateString(),
                'hours' => '',
                'is_billable' => true,
            ]),
            'projects' => $listProjectOptions->handle(),
        ]);
    }

    public function store(Request $request, LogTimeAction $logTime, StartTimerAction $startTimer): RedirectResponse
    {
        $data = TimeEntryData::validateAndCreate($request->all());

        /** @var User $user */
        $user = $request->user();
        $entry = $logTime->handle($data, $user);

        if ($request->boolean('start_timer')) {
            $startTimer->handle($entry);
        }

        return redirect()
            ->route('time-entries.index', ['date' => $entry->spent_on->toDateString()])
            ->with('toast', ['type' => 'success', 'message' => $request->boolean('start_timer') ? 'Timer started.' : 'Time entry saved.']);
    }

    public function edit(Request $request, TimeEntry $timeEntry, ListProjectOptionsAction $listProjectOptions): Response
    {
        abort_unless($timeEntry->user_id === $request->user()?->id, 403);

        $timeEntry->load('project.client');

        return Inertia::render('time-entries/TimeEntryForm', [
            'entry' => TimeEntryData::fromModel($timeEntry),
            'projects' => $listProjectOptions->handle($timeEntry->project_id),
        ]);
    }

    public function update(Request $request, TimeEntry $timeEntry, UpdateTimeEntryAction $updateTimeEntry): RedirectResponse
    {
        abort_unless($timeEntry->user_id === $request->user()?->id, 403);

        $data = TimeEntryData::validateAndCreate($request->all());

        $updateTimeEntry->handle($timeEntry, $data);

        return back()->with('toast', ['type' => 'success', 'message' => 'Time entry saved.']);
    }

    public function destroy(Request $request, TimeEntry $timeEntry, DeleteTimeEntryAction $deleteTimeEntry): RedirectResponse
    {
        abort_unless($timeEntry->user_id === $request->user()?->id, 403);

        $deleteTimeEntry->handle($timeEntry);

        return redirect()
            ->route('time-entries.index', ['date' => $timeEntry->spent_on->toDateString()])
            ->with('toast', ['type' => 'success', 'message' => 'Time entry deleted.']);
    }
}
