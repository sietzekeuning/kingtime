<?php

namespace App\Http\Middleware;

use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Models\TimeEntry;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Controllers flash a toast with `->with('toast', …)`. Inertia only ships
     * its own flash store to the client, so the session toast is handed over
     * before the page renders; `initializeFlashToast` on the front-end shows
     * it. Without this the toast sits in the session and nobody sees it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->session()->has('toast')) {
            Inertia::flash('toast', $request->session()->get('toast'));
        }

        return parent::handle($request, $next);
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // The user's running timer, so the header can show it on every page.
            'runningTimer' => function () use ($request): ?TimeEntryData {
                $user = $request->user();

                if ($user === null) {
                    return null;
                }

                $entry = TimeEntry::query()
                    ->where('user_id', $user->id)
                    ->where('is_running', true)
                    ->with('project.client')
                    ->latest('timer_started_at')
                    ->first();

                return $entry !== null ? TimeEntryData::fromModel($entry) : null;
            },
        ];
    }
}
