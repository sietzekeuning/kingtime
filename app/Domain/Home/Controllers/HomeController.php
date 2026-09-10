<?php

declare(strict_types=1);

namespace App\Domain\Home\Controllers;

use App\Domain\Desktop\Actions\GetLatestMacReleaseAction;
use App\Domain\User\Actions\RegistrationIsOpenAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * kingtime.nl itself: the product page for visitors, the dashboard for
 * whoever is signed in.
 */
class HomeController
{
    public function __invoke(Request $request, RegistrationIsOpenAction $registrationIsOpen, GetLatestMacReleaseAction $latestMacRelease): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('marketing/Home', [
            'canRegister' => $registrationIsOpen->handle(),
            'macRelease' => $latestMacRelease->handle(),
            'repositoryUrl' => 'https://github.com/sietzekeuning/kingtime',
            'macRepositoryUrl' => sprintf('https://github.com/%s', config('kingtime.mac.repository')),
        ]);
    }
}
