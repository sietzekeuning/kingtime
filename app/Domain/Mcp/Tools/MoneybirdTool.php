<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Moneybird\Services\MoneybirdClient;
use Laravel\Mcp\Request;

/**
 * Base of the read-only Moneybird tools: the client, the shared argument
 * rules (Moneybird periods and pagination) and the compact payload shapes
 * so every tool describes an invoice, document or contact the same way.
 *
 * @phpstan-type MoneybirdPayload array<string, mixed>
 */
abstract class MoneybirdTool extends KingtimeTool
{
    /**
     * Moneybird's `period` filter: a relative keyword or a `YYYYMM..YYYYMM`
     * or `YYYYMMDD..YYYYMMDD` range.
     */
    protected const string PERIOD_PATTERN = '/^(?:(?:this|prev|next)_(?:month|quarter|year)|\d{4}(?:\d{2}(?:\d{2})?)?(?:\.\.\d{4}(?:\d{2}(?:\d{2})?)?)?)$/';

    protected const string PERIOD_DESCRIPTION = 'Moneybird period filter: this_month, prev_month, next_month, this_quarter, prev_quarter, this_year, prev_year, or a range such as 202601..202603 (months) or 20260101..20260131 (days).';

    public function __construct(protected MoneybirdClient $moneybird) {}

    /**
     * @return array<string, array<int, string>>
     */
    protected static function listRules(): array
    {
        return [
            'period' => ['nullable', 'string', 'regex:'.self::PERIOD_PATTERN],
            'contact_id' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function page(Request $request): int
    {
        return max(1, (int) ($request->get('page') ?? 1));
    }

    protected function perPage(Request $request, int $default = 50): int
    {
        return min(100, max(1, (int) ($request->get('per_page') ?? $default)));
    }

    /**
     * The `period` and `contact_id` arguments as Moneybird filters.
     *
     * @return array<string, string|null>
     */
    protected function listFilters(Request $request): array
    {
        return [
            'period' => self::optionalString($request, 'period'),
            'contact_id' => self::optionalString($request, 'contact_id'),
        ];
    }

    protected static function optionalString(Request $request, string $key): ?string
    {
        $value = trim((string) ($request->get($key) ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * A contact's display name: the company, else the person.
     *
     * @param  MoneybirdPayload|null  $contact
     */
    protected static function contactName(?array $contact): ?string
    {
        if ($contact === null) {
            return null;
        }

        $company = trim((string) ($contact['company_name'] ?? ''));

        if ($company !== '') {
            return $company;
        }

        $person = trim(sprintf('%s %s', $contact['firstname'] ?? '', $contact['lastname'] ?? ''));

        return $person === '' ? null : $person;
    }

    /**
     * @param  MoneybirdPayload  $contact
     * @return MoneybirdPayload
     */
    protected static function contactPayload(array $contact): array
    {
        return [
            'id' => self::id($contact['id'] ?? null),
            'name' => self::contactName($contact),
            'company_name' => self::nullableString($contact['company_name'] ?? null),
            'firstname' => self::nullableString($contact['firstname'] ?? null),
            'lastname' => self::nullableString($contact['lastname'] ?? null),
            'email' => self::nullableString($contact['email'] ?? null),
            'customer_id' => self::nullableString($contact['customer_id'] ?? null),
        ];
    }

    /**
     * The compact shape of a sales invoice as the list tools return it.
     *
     * @param  MoneybirdPayload  $invoice
     * @return MoneybirdPayload
     */
    protected static function salesInvoicePayload(array $invoice): array
    {
        /** @var MoneybirdPayload|null $contact */
        $contact = is_array($invoice['contact'] ?? null) ? $invoice['contact'] : null;

        return [
            'id' => self::id($invoice['id'] ?? null),
            'invoice_id' => self::nullableString($invoice['invoice_id'] ?? null),
            'contact_id' => self::id($invoice['contact_id'] ?? null),
            'contact' => self::contactName($contact),
            'reference' => self::nullableString($invoice['reference'] ?? null),
            'invoice_date' => self::nullableString($invoice['invoice_date'] ?? null),
            'due_date' => self::nullableString($invoice['due_date'] ?? null),
            'state' => self::nullableString($invoice['state'] ?? null),
            'total_price_excl_tax' => self::money($invoice['total_price_excl_tax'] ?? null),
            'total_price_incl_tax' => self::money($invoice['total_price_incl_tax'] ?? null),
            'total_unpaid' => self::money($invoice['total_unpaid'] ?? null),
            'currency' => self::nullableString($invoice['currency'] ?? null),
            'url' => self::nullableString($invoice['url'] ?? null),
        ];
    }

    /**
     * The compact shape of a purchase invoice or receipt.
     *
     * @param  MoneybirdPayload  $document
     * @return MoneybirdPayload
     */
    protected static function documentPayload(array $document): array
    {
        /** @var MoneybirdPayload|null $contact */
        $contact = is_array($document['contact'] ?? null) ? $document['contact'] : null;

        return [
            'id' => self::id($document['id'] ?? null),
            'reference' => self::nullableString($document['reference'] ?? null),
            'contact_id' => self::id($document['contact_id'] ?? null),
            'contact' => self::contactName($contact),
            'date' => self::nullableString($document['date'] ?? null),
            'due_date' => self::nullableString($document['due_date'] ?? null),
            'state' => self::nullableString($document['state'] ?? null),
            'total_price_excl_tax' => self::money($document['total_price_excl_tax'] ?? null),
            'total_price_incl_tax' => self::money($document['total_price_incl_tax'] ?? null),
            'currency' => self::nullableString($document['currency'] ?? null),
            'url' => self::nullableString($document['url'] ?? null),
        ];
    }

    /**
     * Sums a money field over a list of Moneybird records.
     *
     * @param  array<int, MoneybirdPayload>  $records
     */
    protected static function sum(array $records, string $key): string
    {
        $total = 0.0;

        foreach ($records as $record) {
            $total += (float) ($record[$key] ?? 0);
        }

        return (string) self::decimal($total);
    }

    protected static function money(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::decimal(is_numeric($value) ? $value : 0);
    }

    protected static function id(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    protected static function nullableString(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
