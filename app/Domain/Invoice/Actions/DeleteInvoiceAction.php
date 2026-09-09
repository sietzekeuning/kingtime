<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Invoice\Exceptions\InvoiceLockedException;
use App\Domain\Invoice\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a local draft and gives its hours back to the unbilled pool. An
 * invoice that lives in Moneybird is only deleted with `force`, since
 * Moneybird keeps its copy.
 */
class DeleteInvoiceAction
{
    /**
     * @throws InvoiceLockedException
     */
    public function handle(Invoice $invoice, bool $force = false): void
    {
        if (! $force) {
            if ($invoice->isPushedToMoneybird()) {
                throw InvoiceLockedException::pushed();
            }

            if ($invoice->status !== InvoiceStatus::Draft) {
                throw InvoiceLockedException::notDraft();
            }
        }

        DB::transaction(function () use ($invoice): void {
            $invoice->timeEntries()->update(['invoice_id' => null, 'is_billed' => false, 'is_locked' => false]);
            $invoice->delete();
        });
    }
}
