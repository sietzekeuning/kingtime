<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\User\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class GetRunningTimerTool extends KingtimeTool
{
    protected string $name = 'get_running_timer';

    protected string $title = 'Get running timer';

    protected string $description = 'Tells whether you have a timer running and, if so, on which entry, since when and with how many hours so far.';

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $entry = $user->timeEntries()
            ->where('is_running', true)
            ->with('project.client')
            ->latest('timer_started_at')
            ->first();

        if ($entry === null) {
            return Response::structured(['running' => false, 'entry' => null]);
        }

        return Response::structured([
            'running' => true,
            'elapsed_minutes' => intdiv($entry->elapsedSeconds(), 60),
            'entry' => $this->entryPayload($entry),
        ]);
    }
}
