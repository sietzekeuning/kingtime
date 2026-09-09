<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Controllers;

use App\Domain\Harvest\Actions\StartHarvestImportAction;
use App\Domain\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HarvestImportController
{
    /**
     * Queue an import with the user's own connection and return the initial progress for polling.
     */
    public function store(Request $request, StartHarvestImportAction $startImport): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['progress' => $startImport->handle($user)]);
    }
}
