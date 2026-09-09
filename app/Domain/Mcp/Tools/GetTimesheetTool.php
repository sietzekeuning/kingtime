<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Time\Actions\BuildTimesheetAction;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Data\TimesheetDayData;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class GetTimesheetTool extends KingtimeTool
{
    public function __construct(private BuildTimesheetAction $buildTimesheet) {}

    protected string $name = 'get_timesheet';

    protected string $title = 'Get timesheet';

    protected string $description = 'Your week (Monday to Sunday) around a date: the total per day, the week total, and the full entries of the selected day. Defaults to today. Running timers count their elapsed time.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('A day in the week to show, YYYY-MM-DD. Defaults to today.'),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $timesheet = $this->buildTimesheet->handle($user, $this->dateArgument($request, 'date', CarbonImmutable::today()));

        return Response::structured([
            'selected_date' => $timesheet->selected_date,
            'week_start' => $timesheet->week_start,
            'week_end' => $timesheet->week_end,
            'week_total' => $timesheet->week_total,
            'day_total' => $timesheet->day_total,
            'days' => $timesheet->days->map(fn (TimesheetDayData $day) => [
                'date' => $day->date,
                'weekday' => $day->weekday,
                'total_hours' => $day->total_hours,
                'entries_count' => $day->entries_count,
                'is_today' => $day->is_today,
            ])->values()->all(),
            'entries' => $timesheet->entries->map(fn (TimeEntryData $entry) => $this->entryDataPayload($entry))->values()->all(),
        ]);
    }
}
