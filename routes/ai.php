<?php

use App\Domain\Mcp\Servers\KingtimeServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP servers
|--------------------------------------------------------------------------
|
| The HTTP server is what Claude Desktop, Claude Code and Cursor connect to,
| authenticated with a personal API token (Settings > API tokens):
|
|   claude mcp add --transport http kingtime https://kingtime.nl/mcp \
|       --header "Authorization: Bearer <token>"
|
| The local server speaks stdio for clients running on the same machine
| (`php artisan mcp:start kingtime`). It carries no authentication and acts
| as the first user in the database.
|
*/

Mcp::web('/mcp', KingtimeServer::class)->middleware('auth:sanctum');

Mcp::local('kingtime', KingtimeServer::class);
