<?php

use App\Domain\Harvest\Commands\ImportFromHarvestCommand;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\Invoice\Actions\SyncInvoiceStatusesAction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// -- Invoices: keep the status of pushed invoices in step with Moneybird ---
Artisan::command('invoices:sync-statuses', function (SyncInvoiceStatusesAction $syncStatuses): void {
    $count = $syncStatuses->handle();

    $this->info("Refreshed {$count} invoice(s) from Moneybird.");
})->purpose('Refresh the status of open invoices from Moneybird');

Schedule::command('invoices:sync-statuses')->daily();

// -- Harvest: incremental import every hour once credentials are set -------
Schedule::command(ImportFromHarvestCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->when(fn (): bool => app(HarvestClient::class)->isConfigured());
