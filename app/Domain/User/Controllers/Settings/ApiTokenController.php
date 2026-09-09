<?php

declare(strict_types=1);

namespace App\Domain\User\Controllers\Settings;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal API tokens (Sanctum) that authenticate an LLM against the MCP
 * server at /mcp. The plain-text token is shown once, right after creation.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $user->tokens()->latest()->get()
                ->map(fn (PersonalAccessToken $token) => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'plainTextToken' => $request->session()->get('plainTextToken'),
            'mcpUrl' => url('/mcp'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $token = $user->createToken($validated['name']);

        return to_route('api-tokens.index')
            ->with('plainTextToken', $token->plainTextToken)
            ->with('toast', ['type' => 'success', 'message' => 'API token created. Copy it now; it will not be shown again.']);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->tokens()->whereKey($token)->firstOrFail()->delete();

        return to_route('api-tokens.index')
            ->with('toast', ['type' => 'success', 'message' => 'API token revoked.']);
    }
}
