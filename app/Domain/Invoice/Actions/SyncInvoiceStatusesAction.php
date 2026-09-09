<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use Illuminate\Support\Facades\Log;

/**
 * Daily refresh of every pushed invoice that can still change state in
 * Moneybird (anything that is not paid or written off).
 */
class SyncInvoiceStatusesAction
{
    public function __construct(private SyncInvoiceStatusAction $syncStatus) {}

    /**
     * @return int The number of invoices that were refreshed.
     */
    public function handle(): int
    {
        $synced = 0;

        $invoices = Invoice::query()
            ->with('user.moneybirdConnection')
            ->whereNotNull('moneybird_invoice_id')
            ->whereHas('user.moneybirdConnection')
            ->whereNotIn('status', [InvoiceStatus::Paid, InvoiceStatus::Uncollectible])
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            try {
                $this->syncStatus->handle($invoice);
                $synced++;
            } catch (MoneybirdException $exception) {
                Log::warning("Could not sync invoice #{$invoice->id} with Moneybird: {$exception->getMessage()}");
            }
        }

        return $synced;
    }
}
