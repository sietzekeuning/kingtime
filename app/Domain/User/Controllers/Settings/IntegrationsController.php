<?php

declare(strict_types=1);

namespace App\Domain\User\Controllers\Settings;

use Inertia\Inertia;
use Inertia\Response;

/** Placeholder until the Harvest and Moneybird integrations are built out. */
class IntegrationsController
{
    public function edit(): Response
    {
        return Inertia::render('settings/Integrations');
    }
}
