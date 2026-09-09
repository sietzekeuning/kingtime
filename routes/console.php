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

// Spatie backup to S3. Clean first so a fresh archive is never pushed only to
// be deleted minutes later, then dump the database plus storage/app. The
// health monitor runs an hour later and mails BACKUP_NOTIFICATION_EMAIL when
// the latest backup is missing or stale.
Schedule::command('backup:clean')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('03:00')->withoutOverlapping();
