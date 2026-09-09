<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Invoice\Actions\PrepareInvoiceAction;
use App\Domain\Invoice\Actions\PushInvoiceToMoneybirdAction;
use App\Domain\Mcp\Concerns\BuildsInvoicePayloads;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class PrepareInvoiceTool extends KingtimeTool
{
    use BuildsInvoicePayloads;

    public function __construct(
        private PrepareInvoiceAction $prepareInvoice,
        private PushInvoiceToMoneybirdAction $pushInvoice,
    ) {}

    protected string $name = 'prepare_invoice';

    protected string $title = 'Prepare invoice';

    protected string $description = 'Creates a draft invoice from a client\'s unbilled billable hours in a period and locks those hours to it. Optionally restrict it to specific time_entry_ids. With push_to_moneybird: true the draft is also created in Moneybird (as a draft, nothing is sent to the customer) and the Moneybird URL is returned. Preview with preview_invoice first and confirm with the user before calling this. Locked hours can be released again by deleting the draft in the web app.';

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
            'time_entry_ids' => $schema->array()->items($schema->integer())->description('Only invoice these entries (they must be unbilled and inside the period). Leave out to invoice every unbilled entry in the period.'),
            'notes' => $schema->string()->description('Free text kept on the local invoice; not sent to Moneybird.'),
            'push_to_moneybird' => $schema->boolean()->description('Also create the invoice as a draft in Moneybird.')->default(false),
        ];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        $request->validate([
            ...self::invoicePeriodRules(),
            'time_entry_ids' => ['nullable', 'array'],
            'time_entry_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'push_to_moneybird' => ['nullable', 'boolean'],
        ]);

        $client = $this->resolveClient($request);
        [$from, $to] = $this->invoicePeriod($request);

        /** @var array<int, int|string>|null $timeEntryIds */
        $timeEntryIds = $request->get('time_entry_ids');

        $invoice = $this->prepareInvoice->handle(
            $user,
            $client,
            $from,
            $to,
            $timeEntryIds !== null && $timeEntryIds !== [] ? $timeEntryIds : null,
            $request->get('notes') !== null ? (string) $request->get('notes') : null,
        );

        $moneybirdError = null;

        if ($request->boolean('push_to_moneybird')) {
            try {
                $this->pushInvoice->handle($invoice);
            } catch (MoneybirdException $exception) {
                $moneybirdError = $exception->getMessage();
            }
        }

        $invoice->load(['client', 'lines', 'timeEntries']);

        return Response::structured([
            'message' => match (true) {
                $moneybirdError !== null => 'Draft invoice created locally, but pushing it to Moneybird failed. Fix the cause and push it from the web app.',
                $invoice->isPushedToMoneybird() => 'Draft invoice created and pushed to Moneybird as a draft. Nothing has been sent to the customer.',
                default => 'Draft invoice created locally. It has not been pushed to Moneybird.',
            },
            'pushed_to_moneybird' => $invoice->isPushedToMoneybird(),
            'moneybird_error' => $moneybirdError,
            'invoice' => $this->invoicePayload($invoice),
        ]);
    }
}
