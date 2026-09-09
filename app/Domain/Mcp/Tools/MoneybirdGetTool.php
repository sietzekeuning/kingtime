<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Mcp\Exceptions\McpToolException;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class MoneybirdGetTool extends MoneybirdTool
{
    private const string PATH_PATTERN = '/^[a-z0-9_\/.-]+$/';

    protected string $name = 'moneybird_get';

    protected string $title = 'Moneybird GET request';

    protected string $description = 'Read-only escape hatch for any Moneybird API v2 GET endpoint under the administration that the other moneybird_* tools do not cover. Pass the relative `path` without the administration id and without `.json`, for example `estimates`, `documents/general_journal_documents`, `sales_invoices/123/payments`, `ledger_accounts`, `financial_accounts`, `financial_mutations`, `products`, `tax_rates`, `time_entries` or `projects`. `query` carries Moneybird\'s query parameters: `page`, `per_page` (max 100), `query` (contact search) and `filter` in Moneybird\'s `key:value,key:value` syntax such as `period:this_month,state:open`. The raw Moneybird JSON is returned unchanged. This tool only reads; it cannot create, change or delete anything in Moneybird.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('Relative API path, lower-case letters, digits, `_`, `-`, `.` and `/` only, without a leading slash and without `.json`.')->required(),
            'query' => $schema->object()->description('Query parameters as an object of scalar values, e.g. {"filter": "period:this_month,state:open", "page": 2, "per_page": 100}.'),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate([
            'path' => ['required', 'string', 'max:200'],
            'query' => ['nullable', 'array'],
        ]);

        $path = self::cleanPath((string) $request->get('path'));
        $query = self::cleanQuery($request->get('query'));

        $result = $this->moneybird->get($path, $query);

        return Response::structured([
            'path' => $path,
            'query' => $query === [] ? null : $query,
            'count' => array_is_list($result) ? count($result) : null,
            'result' => $result,
        ]);
    }

    private static function cleanPath(string $path): string
    {
        $path = trim($path);
        $path = trim($path, '/');

        if (str_ends_with($path, '.json')) {
            $path = mb_substr($path, 0, -5);
        }

        if ($path === '' || preg_match(self::PATH_PATTERN, $path) !== 1 || str_contains($path, '..') || str_contains($path, '//')) {
            throw new McpToolException("Invalid path \"{$path}\": use a relative Moneybird API path such as `estimates` or `sales_invoices/123/payments` (lower-case letters, digits, `_`, `-`, `.` and `/`, no `.json`).");
        }

        return $path;
    }

    /**
     * @return array<string, string|int>
     */
    private static function cleanQuery(mixed $query): array
    {
        if (! is_array($query)) {
            return [];
        }

        $clean = [];

        foreach ($query as $key => $value) {
            if (! is_string($key) || preg_match('/^[a-z0-9_\[\]]+$/', $key) !== 1) {
                throw new McpToolException("Invalid query parameter name \"{$key}\".");
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (is_int($value)) {
                $clean[$key] = $value;

                continue;
            }

            if (! is_scalar($value)) {
                throw new McpToolException("Query parameter \"{$key}\" must be a string or number.");
            }

            $clean[$key] = (string) $value;
        }

        return $clean;
    }
}
