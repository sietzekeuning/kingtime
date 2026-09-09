<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Time\Actions\StartTimerAction;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class StartTimerTool extends KingtimeTool
{
    public function __construct(private StartTimerAction $startTimer) {}

    protected string $name = 'start_timer';

    protected string $title = 'Start timer';

    protected string $description = 'Starts (or resumes) the timer on one of your existing time entries. Any other running timer of yours is stopped first and keeps its time. To start a timer on new work, use log_time with start_timer: true. Billed or locked entries cannot be timed.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'time_entry_id' => $schema->integer()->description('Id of the entry to run the timer on.')->required(),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(['time_entry_id' => ['required', 'integer']]);

        $entry = $this->findEntry($user, (int) $request->get('time_entry_id'));
        $wasRunning = $entry->is_running;

        $entry = $this->startTimer->handle($entry)->load(['project.client', 'task']);

        return Response::structured([
            'message' => $wasRunning ? 'Timer was already running.' : 'Timer started.',
            'entry' => $this->entryPayload($entry),
        ]);
    }
}
