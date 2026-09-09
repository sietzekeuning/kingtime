<?php

declare(strict_types=1);

namespace App\Domain\User\Controllers\Settings;

use App\Domain\Harvest\Actions\GetHarvestIntegrationAction;
use App\Domain\Moneybird\Actions\GetMoneybirdIntegrationAction;
use App\Domain\User\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController
{
    public function edit(Request $request, GetHarvestIntegrationAction $harvest, GetMoneybirdIntegrationAction $moneybird): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Integrations', [
            'harvest' => $harvest->handle($user),
            'moneybird' => $moneybird->handle($user),
        ]);
    }
}
