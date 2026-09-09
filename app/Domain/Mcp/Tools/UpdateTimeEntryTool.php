<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Mcp\Exceptions\McpToolException;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Actions\UpdateTimeEntryAction;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateTimeEntryTool extends KingtimeTool
{
    public function __construct(private UpdateTimeEntryAction $updateTimeEntry) {}

    protected string $name = 'update_time_entry';

    protected string $title = 'Update time entry';

    protected string $description = 'Changes one or more fields of one of your time entries: project, date, hours, notes or billability. Only the fields you pass change. Moving an entry to another project re-derives its rate from that project. Entries that are billed or locked cannot be changed. Hours of a running entry are the hours accumulated so far.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'time_entry_id' => $schema->integer()->description('Id of the entry (from list_time_entries or get_timesheet).')->required(),
            'project_id' => $schema->integer()->description('Move the entry to this project.'),
            'spent_on' => $schema->string()->description('New date, YYYY-MM-DD.'),
            'hours' => $schema->number()->description('New hours as a decimal, 0 to 24.')->min(0)->max(24),
            'notes' => $schema->string()->description('New notes.')->nullable(),
            'is_billable' => $schema->boolean()->description('Whether the entry is billable.'),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate([
            'time_entry_id' => ['required', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'spent_on' => ['nullable', 'date_format:Y-m-d'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_billable' => ['nullable', 'boolean'],
        ]);

        $entry = $this->findEntry($user, (int) $request->get('time_entry_id'));

        if ($entry->isLocked()) {
            throw TimeEntryLockedException::for($entry);
        }

        $arguments = $request->all();

        $projectId = $request->get('project_id') !== null ? (int) $request->get('project_id') : $entry->project_id;

        if ($projectId !== $entry->project_id && ! Project::query()->whereKey($projectId)->exists()) {
            throw new McpToolException("No project with id {$projectId}. Use list_projects to find the right id.");
        }

        $data = TimeEntryData::validateAndCreate([
            'project_id' => $projectId,
            'spent_on' => $request->get('spent_on') !== null ? (string) $request->get('spent_on') : $entry->spent_on->toDateString(),
            'hours' => $request->get('hours') !== null ? self::decimal((float) $request->get('hours')) : $entry->hours,
            'notes' => array_key_exists('notes', $arguments)
                ? ($arguments['notes'] !== null ? trim((string) $arguments['notes']) : null)
                : $entry->notes,
            'is_billable' => $request->get('is_billable') !== null ? $request->boolean('is_billable') : null,
        ]);

        $entry = $this->updateTimeEntry->handle($entry, $data)->load('project.client');

        return Response::structured([
            'message' => 'Time entry updated.',
            'entry' => $this->entryPayload($entry),
        ]);
    }
}
