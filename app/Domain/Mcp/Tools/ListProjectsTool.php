<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Project\Models\Project;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class ListProjectsTool extends KingtimeTool
{
    protected string $name = 'list_projects';

    protected string $title = 'List projects';

    protected string $description = 'Lists projects with their client and billing settings. Call this before log_time: the project id is what log_time expects. Filter with client_id or a search term (matched against project name, code and client name). Active projects only unless include_inactive is true.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('Only projects of this client.'),
            'search' => $schema->string()->description('Case-insensitive part of the project name, project code or client name.'),
            'include_inactive' => $schema->boolean()->description('Also return archived projects.')->default(false),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate([
            'client_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'include_inactive' => ['nullable', 'boolean'],
        ]);

        $search = trim((string) $request->get('search', ''));

        $projects = Project::query()
            ->with('client')
            ->when(! $request->boolean('include_inactive'), fn (Builder $query) => $query->where('is_active', true))
            ->when($request->get('client_id') !== null, fn (Builder $query) => $query->where('client_id', (int) $request->get('client_id')))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$search}%"))))
            ->orderBy('name')
            ->get();

        return Response::structured([
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'client_id' => $project->client_id,
                'client' => $project->client->name,
                'is_active' => $project->is_active,
                'is_billable' => $project->is_billable,
                'hourly_rate' => $project->billableRate(),
            ])->values()->all(),
        ]);
    }
}
