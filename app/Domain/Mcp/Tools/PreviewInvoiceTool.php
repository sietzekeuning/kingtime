<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Invoice\Actions\BuildInvoiceSpecificationAction;
use App\Domain\Invoice\Exceptions\NothingToInvoiceException;
use App\Domain\Mcp\Concerns\BuildsInvoicePayloads;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class PreviewInvoiceTool extends KingtimeTool
{
    use BuildsInvoicePayloads;

    public function __construct(private BuildInvoiceSpecificationAction $buildSpecification) {}

    protected string $name = 'preview_invoice';

    protected string $title = 'Preview invoice';

    protected string $description = 'Shows what an invoice for a client and period would contain without creating anything: the lines (one per project, task and rate), subtotal, total hours, the entries behind it, how many entries have no rate yet, and the plain-text hour specification. Identify the client by client_id or client_name. The period defaults to the previous calendar month. Use this before prepare_invoice.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('Id of the client (from list_clients).'),
            'client_name' => $schema->string()->description('(Part of) the client name, when you do not have the id.'),
            'from' => $schema->string()->description('First day of the period, YYYY-MM-DD. Defaults to the first day of the previous month.'),
            'to' => $schema->string()->description('Last day of the period, YYYY-MM-DD. Defaults to the last day of the previous month.'),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate(self::invoicePeriodRules());

        $client = $this->resolveClient($request);
        [$from, $to] = $this->invoicePeriod($request);

        $specification = $this->buildSpecification->handle($client, $from, $to);

        if ($specification->isEmpty()) {
            throw NothingToInvoiceException::forPeriod($client->name, $from->format('d-m-Y'), $to->format('d-m-Y'));
        }

        return Response::structured($this->specificationPayload($specification));
    }
}
