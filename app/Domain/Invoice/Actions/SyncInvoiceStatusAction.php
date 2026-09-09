<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Actions;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\Moneybird\Services\MoneybirdClient;

/**
 * Refreshes status, number, dates and totals of a pushed invoice from
 * Moneybird. An invoice that was never pushed is returned untouched.
 */
class SyncInvoiceStatusAction
{
    public function __construct(private MoneybirdClient $moneybird) {}

    /**
     * @throws MoneybirdException
     */
    public function handle(Invoice $invoice): Invoice
    {
        if ($invoice->moneybird_invoice_id === null) {
            return $invoice;
        }

        $payload = $this->moneybird->getSalesInvoice($invoice->moneybird_invoice_id);

        $invoice->fillFromMoneybird($payload)->save();

        return $invoice;
    }
}
