<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Controllers;

use App\Domain\Harvest\Actions\ConnectHarvestAction;
use App\Domain\Harvest\Data\HarvestConnectionData;
use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HarvestConnectionController
{
    public function store(Request $request, ConnectHarvestAction $connectHarvest): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $connection = $connectHarvest->handle($user, HarvestConnectionData::validateAndCreate($request->all()));

        return back()->with('toast', ['type' => 'success', 'message' => 'Harvest connected as '.($connection->account_name ?? $connection->account_email ?? $connection->account_id).'.']);
    }

    /**
     * Forgets the token. Imported clients, projects and hours stay.
     */
    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->harvestConnection()->delete();
        HarvestImportProgressData::clear($user->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Harvest disconnected. Everything imported so far is kept.']);
    }
}
