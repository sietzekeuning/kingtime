<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Controllers;

use App\Domain\Invoice\Actions\SyncInvoiceStatusAction;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use Illuminate\Http\RedirectResponse;

class InvoiceSyncController
{
    public function __invoke(Invoice $invoice, SyncInvoiceStatusAction $syncStatus): RedirectResponse
    {
        if (! $invoice->isPushedToMoneybird()) {
            return back()->with('toast', ['type' => 'info', 'message' => 'This invoice has not been pushed to Moneybird yet.']);
        }

        try {
            $syncStatus->handle($invoice);
        } catch (MoneybirdException $exception) {
            return back()->with('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Invoice status refreshed: '.$invoice->status->label().'.']);
    }
}
