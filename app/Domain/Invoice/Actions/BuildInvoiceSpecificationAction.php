<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Actions;

use App\Domain\Client\Data\ClientData;
use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Data\InvoiceLineData;
use App\Domain\Invoice\Data\InvoiceSpecificationData;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Time\Data\TimeEntryData;
use App\Domain\Time\Models\TimeEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Turns a client's unbilled billable hours in a period into invoice lines
 * (one per project, task and rate) and a plain-text hour specification.
 * Nothing is persisted; PrepareInvoiceAction does that.
 */
class BuildInvoiceSpecificationAction
{
    /**
     * @param  array<int, int|string>|null  $timeEntryIds  Restrict to these entries (still only unbilled ones in the period).
     */
    public function handle(Client $client, CarbonInterface $from, CarbonInterface $to, ?array $timeEntryIds = null): InvoiceSpecificationData
    {
        $entries = $this->entries($client, $from, $to, $timeEntryIds);
        $lines = $this->lines($entries);

        return new InvoiceSpecificationData(
            client: ClientData::fromModel($client),
            period_starts_on: $from->toDateString(),
            period_ends_on: $to->toDateString(),
            lines: $lines,
            subtotal: self::decimal($lines->sum(fn (InvoiceLineData $line) => (float) $line->amount)),
            total_hours: self::decimal($entries->sum(fn (TimeEntry $entry) => (float) $entry->hours)),
            entries: $entries->map(fn (TimeEntry $entry) => TimeEntryData::fromModel($entry))->values(),
            unpriced_entries: $entries->filter(fn (TimeEntry $entry) => $entry->hourly_rate === null)->count(),
            specification_text: $this->specificationText($client, $from, $to, $entries),
        );
    }

    /**
     * @param  array<int, int|string>|null  $timeEntryIds
     * @return EloquentCollection<int, TimeEntry>
     */
    private function entries(Client $client, CarbonInterface $from, CarbonInterface $to, ?array $timeEntryIds): EloquentCollection
    {
        return TimeEntry::query()
            ->unbilled()
            ->where('is_running', false)
            ->whereHas('project', fn (Builder $query) => $query->where('client_id', $client->id))
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->when($timeEntryIds !== null, fn (Builder $query) => $query->whereIn('id', array_map('intval', $timeEntryIds ?? [])))
            ->with(['project.client', 'task'])
            ->orderBy('spent_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * One line per project, task and hourly rate. Entries without a rate
     * still get a line (at 0.00) so the hours show up on the invoice and the
     * price can be filled in by hand.
     *
     * @param  EloquentCollection<int, TimeEntry>  $entries
     * @return Collection<int, InvoiceLineData>
     */
    private function lines(EloquentCollection $entries): Collection
    {
        return $entries
            ->groupBy(fn (TimeEntry $entry) => implode('|', [$entry->project_id, $entry->task_id ?? 0, $entry->hourly_rate ?? '']))
            ->map(function (EloquentCollection $group): InvoiceLineData {
                /** @var TimeEntry $first */
                $first = $group->first();
                $hours = round($group->sum(fn (TimeEntry $entry) => (float) $entry->hours), 2);
                $rate = $first->hourly_rate !== null ? (float) $first->hourly_rate : 0.0;
                $taskName = $first->task?->name;

                return new InvoiceLineData(
                    id: null,
                    project_id: $first->project_id,
                    task_id: $first->task_id,
                    description: $taskName !== null ? "{$first->project->name} · {$taskName}" : $first->project->name,
                    quantity: self::decimal($hours),
                    unit_price: self::decimal($rate),
                    amount: self::decimal(round($hours * $rate, 2)),
                    project_name: $first->project->name,
                    task_name: $taskName,
                );
            })
            ->sortBy([
                fn (InvoiceLineData $a, InvoiceLineData $b) => strcasecmp((string) $a->project_name, (string) $b->project_name),
                fn (InvoiceLineData $a, InvoiceLineData $b) => strcasecmp((string) $a->task_name, (string) $b->task_name),
                fn (InvoiceLineData $a, InvoiceLineData $b) => (float) $b->unit_price <=> (float) $a->unit_price,
            ])
            ->values()
            ->map(function (InvoiceLineData $line, int $index): InvoiceLineData {
                $line->sort_order = $index;

                return $line;
            });
    }

    /**
     * Per project, per date: the hours and what was done. Goes to Moneybird
     * as a note on the invoice and can be pasted into an email.
     *
     * @param  EloquentCollection<int, TimeEntry>  $entries
     */
    private function specificationText(Client $client, CarbonInterface $from, CarbonInterface $to, EloquentCollection $entries): string
    {
        $blocks = [
            "Hour specification for {$client->name}",
            'Period: '.Invoice::formatPeriod($from, $to),
        ];

        $byProject = $entries
            ->groupBy('project_id')
            ->sortBy(fn (EloquentCollection $group) => mb_strtolower($group->first()?->project->name ?? ''));

        foreach ($byProject as $projectEntries) {
            /** @var EloquentCollection<int, TimeEntry> $projectEntries */
            /** @var TimeEntry $first */
            $first = $projectEntries->first();
            $rows = ["## {$first->project->name}"];

            foreach ($projectEntries->sortBy([['spent_on', 'asc'], ['id', 'asc']]) as $entry) {
                $details = array_filter([$entry->task?->name, $entry->notes !== null ? trim($entry->notes) : null], fn (?string $part) => $part !== null && $part !== '');
                $label = implode(' · ', $details);

                if ($entry->hourly_rate === null) {
                    $label = trim($label.' (no rate)');
                }

                $rows[] = rtrim(sprintf('%s  %6s h  %s', $entry->spent_on->format('d-m-Y'), self::decimal((float) $entry->hours), $label));
            }

            $rows[] = sprintf('Subtotal %s: %s h', $first->project->name, self::decimal($projectEntries->sum(fn (TimeEntry $entry) => (float) $entry->hours)));
            $blocks[] = implode("\n", $rows);
        }

        $blocks[] = sprintf('Total: %s h', self::decimal($entries->sum(fn (TimeEntry $entry) => (float) $entry->hours)));

        return implode("\n\n", $blocks);
    }

    private static function decimal(float|int $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
