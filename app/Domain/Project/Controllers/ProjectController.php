<?php

declare(strict_types=1);

namespace App\Domain\Project\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Placeholder until the feature is built out. */
class ProjectController
{
    public function index(Request $request): Response
    {
        return Inertia::render('projects/ProjectList', ['items' => null]);
    }
}
