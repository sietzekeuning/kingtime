<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Controllers;

use App\Domain\Invoice\Actions\PushInvoiceToMoneybirdAction;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use Illuminate\Http\RedirectResponse;

class InvoicePushController
{
    public function __invoke(Invoice $invoice, PushInvoiceToMoneybirdAction $pushInvoice): RedirectResponse
    {
        if ($invoice->isPushedToMoneybird()) {
            return back()->with('toast', ['type' => 'info', 'message' => 'This invoice is already in Moneybird.']);
        }

        try {
            $pushInvoice->handle($invoice);
        } catch (MoneybirdException $exception) {
            return back()->with('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Invoice pushed to Moneybird as a draft.']);
    }
}
