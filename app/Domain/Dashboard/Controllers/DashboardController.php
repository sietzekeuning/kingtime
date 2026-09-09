<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard');
    }
}
