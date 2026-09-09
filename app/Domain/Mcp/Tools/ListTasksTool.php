<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Project\Models\Task;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class ListTasksTool extends KingtimeTool
{
    protected string $name = 'list_tasks';

    protected string $title = 'List tasks';

    protected string $description = 'Lists the task catalogue (Development, Design, Meeting, ...) with id, name, default billability and default rate. A task can only be logged on a project it is assigned to; list_projects shows which tasks each project has.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_inactive' => $schema->boolean()->description('Also return archived tasks.')->default(false),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(['include_inactive' => ['nullable', 'boolean']]);

        $tasks = Task::query()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();

        return Response::structured([
            'tasks' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'name' => $task->name,
                'is_billable_by_default' => $task->is_billable_by_default,
                'default_hourly_rate' => $task->default_hourly_rate,
                'is_active' => $task->is_active,
            ])->values()->all(),
        ]);
    }
}
