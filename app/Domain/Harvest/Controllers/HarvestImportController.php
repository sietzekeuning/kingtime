<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Controllers;

use App\Domain\Harvest\Actions\StartHarvestImportAction;
use Illuminate\Http\JsonResponse;

class HarvestImportController
{
    /**
     * Queue an import and return the initial progress for polling.
     */
    public function store(StartHarvestImportAction $startImport): JsonResponse
    {
        return response()->json(['progress' => $startImport->handle()]);
    }
}
