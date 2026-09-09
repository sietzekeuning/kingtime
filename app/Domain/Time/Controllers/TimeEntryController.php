<?php

declare(strict_types=1);

namespace App\Domain\Time\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Placeholder until the feature is built out. */
class TimeEntryController
{
    public function index(Request $request): Response
    {
        return Inertia::render('time-entries/TimeEntryList', ['items' => null]);
    }
}
