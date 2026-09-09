<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class ListTimeEntriesTool extends KingtimeTool
{
    protected string $name = 'list_time_entries';

    protected string $title = 'List time entries';

    protected string $description = 'Lists your time entries between two dates (inclusive), newest first, with totals. Defaults to the current month. Filter by project_id or client_id, or set unbilled_only to see billable hours that are not on an invoice yet. Entries carry their id for update_time_entry, delete_time_entry and start_timer.';

    private const int MAX_LIMIT = 500;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('First day, YYYY-MM-DD. Defaults to the first day of the current month.'),
            'to' => $schema->string()->description('Last day, YYYY-MM-DD. Defaults to today.'),
            'project_id' => $schema->integer()->description('Only entries on this project.'),
            'client_id' => $schema->integer()->description('Only entries on projects of this client.'),
            'unbilled_only' => $schema->boolean()->description('Only billable entries that are not on an invoice yet.')->default(false),
            'limit' => $schema->integer()->description('Maximum number of entries to return (1 to 500).')->min(1)->max(self::MAX_LIMIT)->default(200),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'project_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'unbilled_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);

        $today = CarbonImmutable::today();
        $from = $this->dateArgument($request, 'from', $today->startOfMonth());
        $to = $this->dateArgument($request, 'to', $today);
        $limit = $request->get('limit') !== null ? (int) $request->get('limit') : 200;

        $query = $user->timeEntries()
            ->with('project.client')
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->when($request->get('project_id') !== null, fn (Builder $query) => $query->where('project_id', (int) $request->get('project_id')))
            ->when($request->get('client_id') !== null, fn (Builder $query) => $query->whereHas('project', fn (Builder $project) => $project->where('client_id', (int) $request->get('client_id'))))
            ->when($request->boolean('unbilled_only'), fn (Builder $query) => $query->unbilled());

        $all = $query->orderByDesc('spent_on')->orderByDesc('id')->get();
        $entries = $all->take($limit);

        return Response::structured([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'entry_count' => $all->count(),
            'total_hours' => self::decimal($all->sum(fn (TimeEntry $entry) => $entry->currentHours())),
            'billable_hours' => self::decimal($all->where('is_billable', true)->sum(fn (TimeEntry $entry) => $entry->currentHours())),
            'truncated' => $all->count() > $entries->count(),
            'entries' => $entries->map(fn (TimeEntry $entry) => $this->entryPayload($entry))->values()->all(),
        ]);
    }
}
