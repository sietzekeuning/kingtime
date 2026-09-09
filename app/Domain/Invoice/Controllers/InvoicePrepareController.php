<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Controllers;

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Actions\BuildInvoiceSpecificationAction;
use App\Domain\Invoice\Actions\PrepareInvoiceAction;
use App\Domain\Invoice\Actions\PushInvoiceToMoneybirdAction;
use App\Domain\Invoice\Data\PrepareInvoiceData;
use App\Domain\Invoice\Exceptions\NothingToInvoiceException;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "prepare invoice" flow: pick a client and a period, preview the
 * specification (a plain GET with query params, so the preview is
 * shareable and partial reloads work), then create the draft.
 */
class InvoicePrepareController
{
    public function create(Request $request, BuildInvoiceSpecificationAction $buildSpecification): Response
    {
        /** @var User $user */
        $user = $request->user();

        $lastMonth = now()->subMonthNoOverflow();

        $filters = [
            'client_id' => $request->filled('client_id') ? (int) $request->query('client_id') : null,
            'period_starts_on' => $request->query('period_starts_on', $lastMonth->startOfMonth()->toDateString()),
            'period_ends_on' => $request->query('period_ends_on', $lastMonth->endOfMonth()->toDateString()),
            'time_entry_ids' => $request->has('time_entry_ids') ? array_map('intval', (array) $request->query('time_entry_ids')) : null,
        ];

        $specification = null;
        $availableEntries = null;

        if ($filters['client_id'] !== null) {
            $data = PrepareInvoiceData::validateAndCreate($filters);
            $client = Client::query()->findOrFail($data->client_id);
            $from = Date::parse($data->period_starts_on);
            $to = Date::parse($data->period_ends_on);

            $specification = $buildSpecification->handle($client, $from, $to, $data->time_entry_ids);
            $availableEntries = $data->time_entry_ids === null
                ? $specification->entries
                : $buildSpecification->handle($client, $from, $to)->entries;
        }

        return Inertia::render('invoices/InvoicePrepare', [
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $client) => ['id' => $client->id, 'name' => $client->name])
                ->all(),
            'filters' => $filters,
            'specification' => $specification,
            'available_entries' => $availableEntries,
            'moneybird_configured' => $user->moneybirdConnection()->exists(),
        ]);
    }

    public function store(Request $request, PrepareInvoiceAction $prepareInvoice, PushInvoiceToMoneybirdAction $pushInvoice): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = PrepareInvoiceData::validateAndCreate($request->all());
        $client = Client::query()->findOrFail($data->client_id);

        try {
            $invoice = $prepareInvoice->handle(
                $user,
                $client,
                Date::parse($data->period_starts_on),
                Date::parse($data->period_ends_on),
                $data->time_entry_ids,
                $data->notes,
            );
        } catch (NothingToInvoiceException $exception) {
            return back()->withErrors(['period_starts_on' => $exception->getMessage()])->withInput();
        }

        if (! $data->push_to_moneybird) {
            return redirect()->route('invoices.show', $invoice)->with('toast', ['type' => 'success', 'message' => 'Draft invoice created.']);
        }

        try {
            $pushInvoice->handle($invoice);
        } catch (MoneybirdException $exception) {
            return redirect()->route('invoices.show', $invoice)->with('toast', [
                'type' => 'warning',
                'message' => 'Draft invoice created, but pushing it to Moneybird failed: '.$exception->getMessage(),
            ]);
        }

        return redirect()->route('invoices.show', $invoice)->with('toast', ['type' => 'success', 'message' => 'Draft invoice created and pushed to Moneybird.']);
    }
}
