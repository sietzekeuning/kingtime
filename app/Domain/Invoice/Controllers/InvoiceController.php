<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Controllers;

use App\Domain\Invoice\Actions\DeleteInvoiceAction;
use App\Domain\Invoice\Data\InvoiceData;
use App\Domain\Invoice\Exceptions\InvoiceLockedException;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Invoice\Tables\InvoiceTable;
use App\Domain\Moneybird\Services\MoneybirdClient;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController
{
    public function index(Request $request, InvoiceTable $table): Response
    {
        return Inertia::render('invoices/InvoiceList', [
            'items' => $table->getData($request)->through(fn (Invoice $invoice) => InvoiceData::fromModel($invoice)),
        ]);
    }

    public function show(Invoice $invoice, MoneybirdClient $moneybird): Response
    {
        $invoice->load([
            'client',
            'lines.project',
            'lines.task',
            'timeEntries' => fn (HasMany $query) => $query->with(['project.client', 'task'])->orderBy('spent_on')->orderBy('id'),
        ]);

        return Inertia::render('invoices/InvoiceShow', [
            'invoice' => InvoiceData::fromModel($invoice),
            'moneybird_configured' => $moneybird->isConfigured(),
        ]);
    }

    public function destroy(Request $request, Invoice $invoice, DeleteInvoiceAction $deleteInvoice): RedirectResponse
    {
        try {
            $deleteInvoice->handle($invoice, force: $request->boolean('force'));
        } catch (InvoiceLockedException $exception) {
            return back()->with('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect()->route('invoices.index')->with('toast', ['type' => 'success', 'message' => 'Invoice deleted. Its hours are unbilled again.']);
    }
}
