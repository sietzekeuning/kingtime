<?php

declare(strict_types=1);

namespace App\Domain\Project\Controllers;

use App\Domain\Project\Models\Project;
use Illuminate\Http\RedirectResponse;

/**
 * Archiving hides a project from the lists and the project selects without
 * touching its hours, the way Harvest treats an archived project.
 */
class ProjectArchiveController
{
    public function store(Project $project): RedirectResponse
    {
        $project->update(['is_active' => false]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Project archived.']);
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->update(['is_active' => true]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Project restored.']);
    }
}
