<?php

declare(strict_types=1);

use App\Domain\Mcp\Servers\KingtimeServer;
use App\Domain\Mcp\Tools\GetRunningTimerTool;
use App\Domain\Mcp\Tools\LogTimeTool;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Facades\Mcp;

/**
 * @return array<string, mixed>
 */
function mcpInitializePayload(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'pest', 'version' => '1.0'],
        ],
    ];
}

it('rejects MCP requests without a token', function (): void {
    $this->postJson('/mcp', mcpInitializePayload())
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

it('rejects MCP requests with a bogus token', function (): void {
    $this->withToken('1|definitely-not-a-token')
        ->postJson('/mcp', mcpInitializePayload())
        ->assertUnauthorized();
});

it('serves the server to a personal access token', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Claude Desktop')->plainTextToken;

    $this->withToken($token)
        ->postJson('/mcp', mcpInitializePayload())
        ->assertOk()
        ->assertHeader('MCP-Session-Id')
        ->assertJsonPath('result.serverInfo.name', 'Kingtime')
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('result.instructions', fn (string $instructions) => str_contains($instructions, 'log_time'))
            ->etc());

    $this->withToken($token)
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []])
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('result.tools', 21)
            ->where('result.tools.0.name', 'list_clients')
            ->where('result.tools.0.annotations.readOnlyHint', true)
            ->etc());

    expect($user->tokens()->firstOrFail()->last_used_at)->not->toBeNull();
});

it('logs time through the HTTP server as the token owner', function (): void {
    $user = User::factory()->create();
    User::factory()->create();
    $project = Project::factory()->create();
    $token = $user->createToken('Claude Desktop')->plainTextToken;

    $this->withToken($token)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'log_time', 'arguments' => ['project_id' => $project->id, 'hours' => 2]],
        ])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.entry.hours', '2.00');

    expect(TimeEntry::query()->firstOrFail()->user_id)->toBe($user->id);
});

it('registers the local stdio server', function (): void {
    expect(Mcp::getLocalServer('kingtime'))->not->toBeNull();
});

it('acts as the first user when no one is authenticated (local server)', function (): void {
    $first = User::factory()->create();
    User::factory()->create();
    $project = Project::factory()->create();

    KingtimeServer::tool(LogTimeTool::class, ['project_id' => $project->id, 'hours' => 1])->assertOk();
    KingtimeServer::tool(GetRunningTimerTool::class)->assertOk();

    expect(TimeEntry::query()->firstOrFail()->user_id)->toBe($first->id);
});

it('explains that a user account is needed', function (): void {
    KingtimeServer::tool(GetRunningTimerTool::class)
        ->assertHasErrors(['Kingtime has no user account yet']);
});
