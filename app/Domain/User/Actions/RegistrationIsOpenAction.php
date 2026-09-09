<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Models\User;

/**
 * Sign-up stays open until the first user exists, unless the installation
 * explicitly allows more registrations (KINGTIME_ALLOW_REGISTRATION).
 */
class RegistrationIsOpenAction
{
    public function handle(): bool
    {
        return config('kingtime.allow_registration') || User::query()->doesntExist();
    }
}
