<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Controllers;

use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HarvestImportProgressController
{
    /**
     * Current progress of the user's queued import; `progress` is null when none has run.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['progress' => HarvestImportProgressData::load($user->id)]);
    }
}
