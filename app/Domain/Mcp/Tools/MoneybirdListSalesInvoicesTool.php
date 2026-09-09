<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class MoneybirdListSalesInvoicesTool extends MoneybirdTool
{
    /**
     * @var array<int, string>
     */
    private const array STATES = ['draft', 'open', 'scheduled', 'pending_payment', 'late', 'reminded', 'paid', 'uncollectible', 'all'];

    protected string $name = 'moneybird_list_sales_invoices';

    protected string $title = 'List Moneybird sales invoices';

    protected string $description = 'Lists sales invoices in Moneybird, filtered with Moneybird\'s own filter syntax: `state` (draft, open, scheduled, pending_payment, late, reminded, paid, uncollectible or all), `period` (this_month, prev_month, this_quarter, this_year, prev_year, or a 202601..202603 / 20260101..20260131 range) and `contact_id`. Returns per invoice the id, invoice number, contact, dates, state, totals excl/incl VAT, the unpaid amount and the Moneybird url, plus the sums and count over the returned page. Pages are 50 invoices unless per_page says otherwise (max 100); `has_more` tells you to fetch the next page. Use moneybird_get_sales_invoice for the lines and payments of one invoice.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'state' => $schema->string()->enum(self::STATES)->description('Only invoices in this state. Omit for Moneybird\'s default selection, or pass `all`.'),
            'period' => $schema->string()->description(self::PERIOD_DESCRIPTION),
            'contact_id' => $schema->string()->description('Only invoices of this Moneybird contact id (from moneybird_list_contacts).'),
            'page' => $schema->integer()->description('Page number, starting at 1.')->default(1),
            'per_page' => $schema->integer()->description('Invoices per page, 1 to 100.')->default(50),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(self::listRules() + [
            'state' => ['nullable', 'string', 'in:'.implode(',', self::STATES)],
        ]);

        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $filters = $this->listFilters($request) + ['state' => self::optionalString($request, 'state')];

        $invoices = $this->moneybird($user)->salesInvoices($filters, $page, $perPage);

        return Response::structured([
            'filters' => array_filter($filters, fn (?string $value) => $value !== null),
            'page' => $page,
            'per_page' => $perPage,
            'count' => count($invoices),
            'has_more' => count($invoices) >= $perPage,
            'total_excl_tax' => self::sum($invoices, 'total_price_excl_tax'),
            'total_incl_tax' => self::sum($invoices, 'total_price_incl_tax'),
            'total_unpaid' => self::sum($invoices, 'total_unpaid'),
            'invoices' => array_values(array_map(fn (array $invoice): array => self::salesInvoicePayload($invoice), $invoices)),
        ]);
    }
}
