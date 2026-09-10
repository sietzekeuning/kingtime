<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Actions;

use App\Domain\Desktop\Data\DesktopTokenData;
use App\Domain\Desktop\Data\DesktopUserData;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Signs the desktop app in with the user's Kingtime email and password and
 * hands it a personal access token, the same kind Settings > API tokens
 * makes for an MCP client. Accounts with two-factor authentication need
 * their one-time code (or a recovery code) as well; the app asks for it
 * when this action says so.
 */
class IssueDesktopTokenAction
{
    public function __construct(private TwoFactorAuthenticationProvider $twoFactor) {}

    public function handle(string $email, string $password, string $deviceName, ?string $code): DesktopTokenData
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->verifyTwoFactorCode($user, $code);
        }

        $token = $user->createToken("Kingtime for Mac ({$deviceName})");

        return new DesktopTokenData(
            token: $token->plainTextToken,
            user: DesktopUserData::fromModel($user),
        );
    }

    private function verifyTwoFactorCode(User $user, ?string $code): void
    {
        $code = trim((string) $code);

        if ($code === '') {
            throw ValidationException::withMessages(['code' => 'Enter the code from your authenticator app.']);
        }

        if ($this->twoFactor->verify(decrypt((string) $user->two_factor_secret), $code)) {
            return;
        }

        if (in_array($code, $user->recoveryCodes(), true)) {
            $user->replaceRecoveryCode($code);

            return;
        }

        throw ValidationException::withMessages(['code' => 'The two-factor code is not valid.']);
    }
}
