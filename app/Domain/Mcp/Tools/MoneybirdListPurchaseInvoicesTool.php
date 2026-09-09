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
class MoneybirdListPurchaseInvoicesTool extends MoneybirdTool
{
    protected string $name = 'moneybird_list_purchase_invoices';

    protected string $title = 'List Moneybird purchase invoices';

    protected string $description = 'Lists purchase invoices (incoming supplier invoices) in Moneybird, filtered with Moneybird\'s filter syntax: `period` (this_month, prev_month, this_quarter, this_year, prev_year, or a 202601..202603 / 20260101..20260131 range) and `contact_id`. Returns per document the id, reference, supplier, date, due date, state, totals excl/incl VAT and the Moneybird url, plus the sums over the returned page. Pages are 50 documents (per_page up to 100).';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->description(self::PERIOD_DESCRIPTION),
            'contact_id' => $schema->string()->description('Only documents of this Moneybird contact id (the supplier).'),
            'page' => $schema->integer()->description('Page number, starting at 1.')->default(1),
            'per_page' => $schema->integer()->description('Documents per page, 1 to 100.')->default(50),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(self::listRules());

        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $filters = $this->listFilters($request);

        $documents = $this->moneybird->purchaseInvoices($filters, $page, $perPage);

        return Response::structured([
            'filters' => array_filter($filters, fn (?string $value) => $value !== null),
            'page' => $page,
            'per_page' => $perPage,
            'count' => count($documents),
            'has_more' => count($documents) >= $perPage,
            'total_excl_tax' => self::sum($documents, 'total_price_excl_tax'),
            'total_incl_tax' => self::sum($documents, 'total_price_incl_tax'),
            'purchase_invoices' => array_values(array_map(fn (array $document): array => self::documentPayload($document), $documents)),
        ]);
    }
}
