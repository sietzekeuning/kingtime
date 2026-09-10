<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Controllers;

use App\Domain\Desktop\Actions\BuildDesktopStateAction;
use App\Domain\Desktop\Data\DesktopStateData;
use App\Domain\User\Models\User;
use Illuminate\Http\Request;

class DesktopStateController
{
    public function __invoke(Request $request, BuildDesktopStateAction $buildState): DesktopStateData
    {
        /** @var User $user */
        $user = $request->user();

        return $buildState->handle($user);
    }
}
