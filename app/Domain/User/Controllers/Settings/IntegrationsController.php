<?php

declare(strict_types=1);

namespace App\Domain\User\Controllers\Settings;

use App\Domain\Harvest\Actions\GetHarvestIntegrationAction;
use App\Domain\Moneybird\Services\MoneybirdClient;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController
{
    public function edit(GetHarvestIntegrationAction $harvest, MoneybirdClient $moneybird): Response
    {
        return Inertia::render('settings/Integrations', [
            'harvest' => $harvest->handle(),
            'moneybird' => [
                'configured' => $moneybird->isConfigured(),
            ],
        ]);
    }
}
