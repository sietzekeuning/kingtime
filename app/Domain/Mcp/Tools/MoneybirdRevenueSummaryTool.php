<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Moneybird\Services\MoneybirdClient;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class MoneybirdRevenueSummaryTool extends MoneybirdTool
{
    private const int PER_PAGE = 100;

    private const int MAX_PAGES = 20;

    /**
     * States that are not revenue: unsent drafts and written-off invoices.
     *
     * @var array<int, string>
     */
    private const array EXCLUDED_STATES = ['draft', 'uncollectible'];

    protected string $name = 'moneybird_revenue_summary';

    protected string $title = 'Moneybird revenue summary';

    protected string $description = 'Sums the sales invoices in Moneybird for a period (default this_year) per month and per contact: invoiced amount excl. VAT, the amount still unpaid, and the number of invoices. Drafts and uncollectible invoices are left out; all other states count (open, late, paid, ...). Pages through every invoice in the period, so it is the tool for "how much did I invoice in Q2" or "who are my biggest customers this year". `period` uses Moneybird\'s syntax: this_year, prev_year, this_quarter, this_month, prev_month, or a 202601..202606 / 20260101..20260630 range.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->description(self::PERIOD_DESCRIPTION.' Defaults to this_year.')->default('this_year'),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(['period' => ['nullable', 'string', 'regex:'.self::PERIOD_PATTERN]]);

        $period = self::optionalString($request, 'period') ?? 'this_year';
        [$invoices, $capped] = $this->fetchAll($this->moneybird($user), $period);

        $months = [];
        $contacts = [];
        $excluded = 0;
        $currencies = [];

        foreach ($invoices as $invoice) {
            $state = (string) ($invoice['state'] ?? '');

            if (in_array($state, self::EXCLUDED_STATES, true)) {
                $excluded++;

                continue;
            }

            $month = mb_substr((string) ($invoice['invoice_date'] ?? ''), 0, 7);
            $month = $month === '' ? 'unknown' : $month;
            /** @var array<string, mixed>|null $contact */
            $contact = is_array($invoice['contact'] ?? null) ? $invoice['contact'] : null;
            $contactId = self::id($invoice['contact_id'] ?? null) ?? 'unknown';
            $currency = self::nullableString($invoice['currency'] ?? null);

            if ($currency !== null) {
                $currencies[$currency] = true;
            }

            $months[$month] ??= ['month' => $month, 'total_excl_tax' => 0.0, 'total_unpaid' => 0.0, 'invoice_count' => 0];
            $months[$month]['total_excl_tax'] += (float) ($invoice['total_price_excl_tax'] ?? 0);
            $months[$month]['total_unpaid'] += (float) ($invoice['total_unpaid'] ?? 0);
            $months[$month]['invoice_count']++;

            $contacts[$contactId] ??= ['contact_id' => $contactId, 'contact' => self::contactName($contact), 'total_excl_tax' => 0.0, 'total_unpaid' => 0.0, 'invoice_count' => 0];
            $contacts[$contactId]['contact'] ??= self::contactName($contact);
            $contacts[$contactId]['total_excl_tax'] += (float) ($invoice['total_price_excl_tax'] ?? 0);
            $contacts[$contactId]['total_unpaid'] += (float) ($invoice['total_unpaid'] ?? 0);
            $contacts[$contactId]['invoice_count']++;
        }

        ksort($months);
        uasort($contacts, fn (array $a, array $b): int => $b['total_excl_tax'] <=> $a['total_excl_tax']);

        $counted = count($invoices) - $excluded;

        return Response::structured([
            'period' => $period,
            'excluded_states' => self::EXCLUDED_STATES,
            'invoice_count' => $counted,
            'excluded_count' => $excluded,
            'currencies' => array_keys($currencies),
            'total_excl_tax' => self::sumFloat(array_column($months, 'total_excl_tax')),
            'total_unpaid' => self::sumFloat(array_column($months, 'total_unpaid')),
            'capped' => $capped,
            'note' => $capped
                ? sprintf('Only the first %d pages of %d invoices were summed; narrow the period for exact totals.', self::MAX_PAGES, self::PER_PAGE)
                : null,
            'per_month' => array_values(array_map(fn (array $row): array => self::formatRow($row), $months)),
            'per_contact' => array_values(array_map(fn (array $row): array => self::formatRow($row), $contacts)),
        ]);
    }

    /**
     * Every sales invoice in the period, page by page, up to MAX_PAGES.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: bool}
     */
    private function fetchAll(MoneybirdClient $moneybird, string $period): array
    {
        $all = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $invoices = $moneybird->salesInvoices(['period' => $period, 'state' => 'all'], $page, self::PER_PAGE);
            $all = [...$all, ...$invoices];

            if (count($invoices) < self::PER_PAGE) {
                return [$all, false];
            }
        }

        return [$all, true];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function formatRow(array $row): array
    {
        $row['total_excl_tax'] = self::decimal((float) $row['total_excl_tax']);
        $row['total_unpaid'] = self::decimal((float) $row['total_unpaid']);

        return $row;
    }

    /**
     * @param  array<int, float>  $values
     */
    private static function sumFloat(array $values): string
    {
        return (string) self::decimal(array_sum($values));
    }
}
