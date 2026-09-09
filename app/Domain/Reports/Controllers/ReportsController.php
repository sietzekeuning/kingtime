<?php

declare(strict_types=1);

namespace App\Domain\Reports\Controllers;

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Reports\Actions\BuildReportsAction;
use App\Domain\Reports\Data\ReportsFilterData;
use App\Domain\User\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hours and money per week, month or year. The filter lives in the query
 * string so a report is shareable and the page reloads in place.
 */
class ReportsController
{
    public function __invoke(Request $request, BuildReportsAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filter = ReportsFilterData::validateAndCreate($request->query());

        return Inertia::render('reports/ReportsIndex', [
            'reports' => $action->handle($user, $filter),
            'filter' => $filter,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
                ->all(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'client_id'])
                ->map(fn (Project $project): array => ['id' => $project->id, 'name' => $project->name, 'client_id' => $project->client_id])
                ->all(),
        ]);
    }
}
