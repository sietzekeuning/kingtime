<?php

declare(strict_types=1);

namespace App\Domain\Home\Controllers;

use App\Domain\Desktop\Actions\GetLatestMacReleaseAction;
use App\Domain\User\Actions\RegistrationIsOpenAction;
use Inertia\Inertia;
use Inertia\Response;

/**
 * kingtime.nl itself: the product page. Signed-in users see it too, with a
 * button to the dashboard where visitors get the sign-in links.
 */
class HomeController
{
    public function __invoke(RegistrationIsOpenAction $registrationIsOpen, GetLatestMacReleaseAction $latestMacRelease): Response
    {
        return Inertia::render('marketing/Home', [
            'canRegister' => $registrationIsOpen->handle(),
            'macRelease' => $latestMacRelease->handle(),
            'repositoryUrl' => 'https://github.com/sietzekeuning/kingtime',
            'macRepositoryUrl' => sprintf('https://github.com/%s', config('kingtime.mac.repository')),
        ]);
    }
}
