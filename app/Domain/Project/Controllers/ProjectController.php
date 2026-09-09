<?php

declare(strict_types=1);

namespace App\Domain\Project\Controllers;

use App\Domain\Client\Data\ClientData;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Data\ProjectData;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Tables\ProjectTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController
{
    public function index(Request $request, ProjectTable $table): Response
    {
        return Inertia::render('projects/ProjectList', [
            'items' => $table->getData($request)->through(fn (Project $project) => ProjectData::fromModel($project)),
            'clients' => Client::query()->orderBy('name')->get()->map(fn (Client $client) => ClientData::fromModel($client)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('projects/ProjectForm', [
            'project' => ProjectData::empty(['client_id' => null]),
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get()->map(fn (Client $client) => ClientData::fromModel($client)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = ProjectData::validateAndCreate($request->all());

        $project = Project::create($data->toUpdateArray());

        return redirect()->route('projects.edit', $project)->with('toast', ['type' => 'success', 'message' => 'Project created.']);
    }

    public function edit(Project $project): Response
    {
        $project->load('client')->loadSum('timeEntries as total_hours', 'hours');

        return Inertia::render('projects/ProjectForm', [
            'project' => ProjectData::fromModel($project),
            'clients' => Client::query()
                ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $project->client_id))
                ->orderBy('name')
                ->get()
                ->map(fn (Client $client) => ClientData::fromModel($client)),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = ProjectData::validateAndCreate($request->all());

        $project->update($data->toUpdateArray());

        return back()->with('toast', ['type' => 'success', 'message' => 'Project saved.']);
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('projects.index')->with('toast', ['type' => 'success', 'message' => 'Project deleted.']);
    }
}
