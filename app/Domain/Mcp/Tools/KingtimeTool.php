<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Exceptions\NothingToInvoiceException;
use App\Domain\Mcp\Exceptions\McpToolException;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Exceptions\TimeEntryLockedException;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Base of every Kingtime MCP tool: resolves the acting user, turns domain
 * exceptions into tool errors the LLM can act on, and shares the compact
 * payload shapes so every tool describes an entry the same way.
 */
abstract class KingtimeTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        try {
            return $this->execute($request, $this->actingUser($request));
        } catch (McpToolException|TimeEntryLockedException|NothingToInvoiceException|MoneybirdException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    abstract protected function execute(Request $request, User $user): Response|ResponseFactory;

    /**
     * The Sanctum user behind the HTTP server. The local (stdio) server
     * carries no authentication, so it acts as the first user in the database.
     */
    protected function actingUser(Request $request): User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        $firstUser = User::query()->orderBy('id')->first();

        if ($firstUser === null) {
            throw new McpToolException('Kingtime has no user account yet. Register one in the web app first.');
        }

        return $firstUser;
    }

    /**
     * A time entry of the acting user, with the relations the payload needs.
     */
    protected function findEntry(User $user, int $id): TimeEntry
    {
        $entry = $user->timeEntries()->with('project.client')->find($id);

        if ($entry === null) {
            throw new McpToolException("No time entry with id {$id} exists for you. Use list_time_entries to find the right id.");
        }

        return $entry;
    }

    /**
     * A client by `client_id`, or by (part of) its name when only
     * `client_name` is given. Ambiguity is reported instead of guessed.
     */
    protected function resolveClient(Request $request): Client
    {
        $clientId = $request->get('client_id');

        if ($clientId !== null && $clientId !== '') {
            $client = Client::query()->find((int) $clientId);

            if ($client === null) {
                throw new McpToolException("No client with id {$clientId}. Use list_clients to find the right id.");
            }

            return $client;
        }

        $name = trim((string) $request->get('client_name', ''));

        if ($name === '') {
            throw new McpToolException('Pass client_id or client_name. Use list_clients to see the clients.');
        }

        $matches = Client::query()->where('name', 'like', "%{$name}%")->orderBy('name')->get();
        $exact = $matches->first(fn (Client $client) => mb_strtolower($client->name) === mb_strtolower($name));

        if ($exact !== null) {
            return $exact;
        }

        if ($matches->count() === 1) {
            return $matches->firstOrFail();
        }

        if ($matches->isEmpty()) {
            throw new McpToolException("No client matches \"{$name}\". Use list_clients to see the clients.");
        }

        $options = $matches->map(fn (Client $client) => "#{$client->id} {$client->name}")->implode(', ');

        throw new McpToolException("Several clients match \"{$name}\": {$options}. Pass client_id to pick one.");
    }

    /**
     * A validated `YYYY-MM-DD` argument as a date, or the default when absent.
     */
    protected function dateArgument(Request $request, string $key, CarbonImmutable $default): CarbonImmutable
    {
        $value = $request->get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return CarbonImmutable::parse((string) $value)->startOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    protected function entryPayload(TimeEntry $entry): array
    {
        return $this->entryDataPayload(TimeEntryData::fromModel($entry));
    }

    /**
     * The compact shape of a time entry as every tool returns it. Hours and
     * rates are strings with two decimals; running entries report their
     * live hours.
     *
     * @return array<string, mixed>
     */
    protected function entryDataPayload(TimeEntryData $entry): array
    {
        return [
            'id' => $entry->id,
            'spent_on' => $entry->spent_on,
            'hours' => $entry->hours,
            'project_id' => $entry->project_id,
            'project' => $entry->project_name,
            'client_id' => $entry->client_id,
            'client' => $entry->client_name,
            'notes' => $entry->notes,
            'is_billable' => $entry->is_billable,
            'is_billed' => $entry->is_billed,
            'is_locked' => $entry->is_locked,
            'is_running' => $entry->is_running,
            'timer_started_at' => $entry->timer_started_at,
            'hourly_rate' => $entry->hourly_rate,
            'invoice_id' => $entry->invoice_id,
        ];
    }

    protected static function decimal(float|int|string|null $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }
}
