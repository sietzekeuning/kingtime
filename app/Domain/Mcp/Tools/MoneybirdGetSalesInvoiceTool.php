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
class MoneybirdGetSalesInvoiceTool extends MoneybirdTool
{
    protected string $name = 'moneybird_get_sales_invoice';

    protected string $title = 'Get Moneybird sales invoice';

    protected string $description = 'Returns one sales invoice from Moneybird in full: contact, dates, state, reference, totals, every line (description, quantity, unit price, line total, VAT rate id), the payments registered against it, its internal notes and the Moneybird url. `id` is the Moneybird invoice id (the long number from moneybird_list_sales_invoices), not the invoice number.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Moneybird id of the sales invoice.')->required(),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(['id' => ['required', 'string', 'regex:/^[0-9]+$/']]);

        $invoice = $this->moneybird->getSalesInvoice((string) $request->get('id'));

        /** @var array<string, mixed>|null $contact */
        $contact = is_array($invoice['contact'] ?? null) ? $invoice['contact'] : null;
        /** @var array<int, array<string, mixed>> $details */
        $details = is_array($invoice['details'] ?? null) ? $invoice['details'] : [];
        /** @var array<int, array<string, mixed>> $payments */
        $payments = is_array($invoice['payments'] ?? null) ? $invoice['payments'] : [];
        /** @var array<int, array<string, mixed>> $notes */
        $notes = is_array($invoice['notes'] ?? null) ? $invoice['notes'] : [];

        return Response::structured(self::salesInvoicePayload($invoice) + [
            'contact_email' => self::nullableString($contact['email'] ?? null),
            'paid_at' => self::nullableString($invoice['paid_at'] ?? null),
            'sent_at' => self::nullableString($invoice['sent_at'] ?? null),
            'prices_are_incl_tax' => (bool) ($invoice['prices_are_incl_tax'] ?? false),
            'total_tax' => self::money($invoice['total_tax'] ?? null),
            'total_paid' => self::money($invoice['total_paid'] ?? null),
            'payment_conditions' => self::nullableString($invoice['payment_conditions'] ?? null),
            'lines' => array_values(array_map(fn (array $line): array => [
                'id' => self::id($line['id'] ?? null),
                'description' => self::nullableString($line['description'] ?? null),
                'amount' => self::nullableString($line['amount'] ?? null),
                'quantity' => self::money($line['amount_decimal'] ?? null),
                'price' => self::money($line['price'] ?? null),
                'total' => self::money($line['total_price_excl_tax_with_discount'] ?? $line['total_price_excl_tax'] ?? null),
                'tax_rate_id' => self::id($line['tax_rate_id'] ?? null),
                'ledger_account_id' => self::id($line['ledger_account_id'] ?? null),
                'period' => self::nullableString($line['period'] ?? null),
            ], $details)),
            'payments' => array_values(array_map(fn (array $payment): array => [
                'id' => self::id($payment['id'] ?? null),
                'payment_date' => self::nullableString($payment['payment_date'] ?? null),
                'price' => self::money($payment['price'] ?? null),
                'price_base' => self::money($payment['price_base'] ?? null),
                'financial_account_id' => self::id($payment['financial_account_id'] ?? null),
                'manual_payment_method' => self::nullableString($payment['manual_payment_method'] ?? null),
            ], $payments)),
            'notes' => array_values(array_map(fn (array $note): array => [
                'id' => self::id($note['id'] ?? null),
                'note' => self::nullableString($note['note'] ?? null),
                'todo' => (bool) ($note['todo'] ?? false),
                'created_at' => self::nullableString($note['created_at'] ?? null),
            ], $notes)),
        ]);
    }
}
