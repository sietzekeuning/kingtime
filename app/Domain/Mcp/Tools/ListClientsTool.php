<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Client\Models\Client;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class ListClientsTool extends KingtimeTool
{
    protected string $name = 'list_clients';

    protected string $title = 'List clients';

    protected string $description = 'Lists the clients (customers) with their id, name and currency. Active clients only unless include_inactive is true. Use the id for list_projects, list_time_entries, preview_invoice and prepare_invoice.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_inactive' => $schema->boolean()->description('Also return archived clients.')->default(false),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(['include_inactive' => ['nullable', 'boolean']]);

        $clients = Client::query()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();

        return Response::structured([
            'clients' => $clients->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'currency' => $client->currency,
                'is_active' => $client->is_active,
            ])->values()->all(),
        ]);
    }
}
