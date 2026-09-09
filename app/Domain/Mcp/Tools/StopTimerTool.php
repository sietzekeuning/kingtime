<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Mcp\Exceptions\McpToolException;
use App\Domain\Time\Actions\StopTimerAction;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class StopTimerTool extends KingtimeTool
{
    public function __construct(private StopTimerAction $stopTimer) {}

    protected string $name = 'stop_timer';

    protected string $title = 'Stop timer';

    protected string $description = 'Stops a running timer and folds the elapsed time into the entry\'s hours. Without time_entry_id it stops whatever timer of yours is running. Returns the entry with its final hours.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'time_entry_id' => $schema->integer()->description('Id of the running entry. Leave out to stop the running timer, whichever entry it is on.'),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(['time_entry_id' => ['nullable', 'integer']]);

        $entry = $request->get('time_entry_id') !== null
            ? $this->findEntry($user, (int) $request->get('time_entry_id'))
            : $this->runningEntry($user);

        if (! $entry->is_running) {
            throw new McpToolException("Time entry #{$entry->id} has no running timer.");
        }

        $entry = $this->stopTimer->handle($entry)->load('project.client');

        return Response::structured([
            'message' => "Timer stopped at {$entry->hours} hours.",
            'entry' => $this->entryPayload($entry),
        ]);
    }

    private function runningEntry(User $user): TimeEntry
    {
        $entry = $user->timeEntries()->where('is_running', true)->with('project.client')->latest('timer_started_at')->first();

        if ($entry === null) {
            throw new McpToolException('No timer is running.');
        }

        return $entry;
    }
}
