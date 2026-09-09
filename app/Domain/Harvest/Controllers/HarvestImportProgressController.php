<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Controllers;

use App\Domain\Harvest\Data\HarvestImportProgressData;
use Illuminate\Http\JsonResponse;

class HarvestImportProgressController
{
    /**
     * Current progress of the queued import; `progress` is null when none has run.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(['progress' => HarvestImportProgressData::load()]);
    }
}
