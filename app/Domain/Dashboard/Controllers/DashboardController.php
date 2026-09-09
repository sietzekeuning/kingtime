<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Controllers;

use App\Domain\Dashboard\Actions\BuildDashboardAction;
use App\Domain\Dashboard\Data\DashboardData;
use App\Domain\User\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __invoke(Request $request, BuildDashboardAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();
        $range = $request->string('range', BuildDashboardAction::DEFAULT_RANGE)->toString();

        $dashboard = null;
        $resolve = function () use (&$dashboard, $action, $user, $range): DashboardData {
            return $dashboard ??= $action->handle($user, $range);
        };

        return Inertia::render('Dashboard', [
            'stats' => fn () => $resolve()->stats,
            'chart' => fn () => $resolve()->chart,
            'statistics' => fn () => $resolve()->statistics,
            'recent_entries' => fn () => $resolve()->recent_entries,
            'top_projects' => fn () => $resolve()->top_projects,
        ]);
    }
}
