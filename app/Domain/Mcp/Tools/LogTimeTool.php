<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Mcp\Exceptions\McpToolException;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Actions\LogTimeAction;
use App\Domain\Time\Actions\StartTimerAction;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class LogTimeTool extends KingtimeTool
{
    public function __construct(
        private LogTimeAction $logTime,
        private StartTimerAction $startTimer,
    ) {}

    protected string $name = 'log_time';

    protected string $title = 'Log time';

    protected string $description = 'Creates a time entry for you on a project. Hours are decimal (1.5 = 90 minutes); spent_on defaults to today. Rate and billability are inherited from the project unless is_billable is given. Set start_timer to true to start a running timer on the new entry (hours may then be 0). Use list_projects first to find the project id.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Id of the project (from list_projects).')->required(),
            'spent_on' => $schema->string()->description('Date of the work, YYYY-MM-DD. Defaults to today.'),
            'hours' => $schema->number()->description('Hours as a decimal, 0 to 24 (e.g. 1.5). Required unless start_timer is true.')->min(0)->max(24),
            'notes' => $schema->string()->description('What was done. Shown on the invoice specification.'),
            'is_billable' => $schema->boolean()->description('Override the project default. Leave out to inherit.'),
            'start_timer' => $schema->boolean()->description('Start a running timer on the new entry.')->default(false),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate([
            'project_id' => ['required', 'integer'],
            'spent_on' => ['nullable', 'date_format:Y-m-d'],
            'hours' => ['required_unless:start_timer,true', 'nullable', 'numeric', 'min:0', 'max:24'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_billable' => ['nullable', 'boolean'],
            'start_timer' => ['nullable', 'boolean'],
        ]);

        $project = $this->findProject((int) $request->get('project_id'));

        $data = TimeEntryData::validateAndCreate([
            'project_id' => $project->id,
            'spent_on' => $this->dateArgument($request, 'spent_on', CarbonImmutable::today())->toDateString(),
            'hours' => self::decimal($request->get('hours') !== null ? (float) $request->get('hours') : 0.0),
            'notes' => $request->get('notes') !== null ? trim((string) $request->get('notes')) : null,
            'is_billable' => $request->get('is_billable') !== null ? $request->boolean('is_billable') : null,
        ]);

        $entry = $this->logTime->handle($data, $user);

        if ($request->boolean('start_timer')) {
            $this->startTimer->handle($entry);
        }

        // A freshly created model only carries the attributes it was given;
        // the database defaults (is_billed, is_locked, ...) need a reload.
        $entry->refresh()->load('project.client');

        return Response::structured([
            'message' => $request->boolean('start_timer') ? 'Timer started.' : 'Time entry logged.',
            'entry' => $this->entryPayload($entry),
        ]);
    }

    private function findProject(int $projectId): Project
    {
        $project = Project::query()->find($projectId);

        if ($project === null) {
            throw new McpToolException("No project with id {$projectId}. Use list_projects to find the right id.");
        }

        return $project;
    }
}
