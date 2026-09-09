<?php

declare(strict_types=1);

namespace App\Domain\User\Controllers\Settings;

use App\Domain\Harvest\Actions\GetHarvestIntegrationAction;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController
{
    public function edit(GetHarvestIntegrationAction $harvest): Response
    {
        return Inertia::render('settings/Integrations', [
            'harvest' => $harvest->handle(),
            'moneybird' => [
                'configured' => config('services.moneybird.access_token') !== null
                    && config('services.moneybird.administration_id') !== null,
            ],
        ]);
    }
}
