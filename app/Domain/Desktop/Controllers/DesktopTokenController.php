<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Controllers;

use App\Domain\Desktop\Actions\IssueDesktopTokenAction;
use App\Domain\Desktop\Data\DesktopTokenData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sign-in and sign-out of the desktop app. The token it gets is a Sanctum
 * personal access token, listed (and revocable) under Settings > API tokens.
 */
class DesktopTokenController
{
    public function store(Request $request, IssueDesktopTokenAction $issueToken): DesktopTokenData
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
        ]);

        return $issueToken->handle(
            email: $validated['email'],
            password: $validated['password'],
            deviceName: $validated['device_name'],
            code: $validated['code'] ?? null,
        );
    }

    public function destroy(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
