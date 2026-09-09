<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class GetUnbilledSummaryTool extends KingtimeTool
{
    protected string $name = 'get_unbilled_summary';

    protected string $title = 'Get unbilled summary';

    protected string $description = 'What can be invoiced: per client the unbilled billable hours, the amount at the entries\' rates (excluding VAT), the number of entries and the oldest and newest date. Running timers are left out until they are stopped. Optionally limit to one client or to entries up to a date. Follow up with preview_invoice for the details.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('Only this client.'),
            'until' => $schema->string()->description('Only entries on or before this date, YYYY-MM-DD.'),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate([
            'client_id' => ['nullable', 'integer'],
            'until' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $entries = TimeEntry::query()
            ->unbilled()
            ->where('is_running', false)
            ->with('project.client')
            ->when($request->get('client_id') !== null, fn (Builder $query) => $query->whereHas('project', fn (Builder $project) => $project->where('client_id', (int) $request->get('client_id'))))
            ->when($request->get('until') !== null, fn (Builder $query) => $query->whereDate('spent_on', '<=', (string) $request->get('until')))
            ->get();

        $clients = $entries
            ->groupBy(fn (TimeEntry $entry) => $entry->project->client_id)
            ->map(function (Collection $group): array {
                /** @var TimeEntry $first */
                $first = $group->first();

                return [
                    'client_id' => $first->project->client_id,
                    'client' => $first->project->client->name,
                    'currency' => $first->project->client->currency,
                    'hours' => self::decimal($group->sum(fn (TimeEntry $entry) => (float) $entry->hours)),
                    'amount' => self::decimal($group->sum(fn (TimeEntry $entry) => $entry->billableAmount())),
                    'entry_count' => $group->count(),
                    'unpriced_entries' => $group->filter(fn (TimeEntry $entry) => $entry->hourly_rate === null)->count(),
                    'oldest_date' => $group->min(fn (TimeEntry $entry) => $entry->spent_on->toDateString()),
                    'newest_date' => $group->max(fn (TimeEntry $entry) => $entry->spent_on->toDateString()),
                ];
            })
            ->sortBy('client', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return Response::structured([
            'clients' => $clients->all(),
            'total_hours' => self::decimal($entries->sum(fn (TimeEntry $entry) => (float) $entry->hours)),
            'total_amount' => self::decimal($entries->sum(fn (TimeEntry $entry) => $entry->billableAmount())),
            'entry_count' => $entries->count(),
        ]);
    }
}
