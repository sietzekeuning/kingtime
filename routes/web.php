<?php

use App\Domain\Client\Controllers\ClientController;
use App\Domain\Dashboard\Controllers\DashboardController;
use App\Domain\Harvest\Controllers\HarvestImportController;
use App\Domain\Harvest\Controllers\HarvestImportProgressController;
use App\Domain\Invoice\Controllers\InvoiceController;
use App\Domain\Invoice\Controllers\InvoicePrepareController;
use App\Domain\Invoice\Controllers\InvoicePushController;
use App\Domain\Invoice\Controllers\InvoiceSyncController;
use App\Domain\Project\Controllers\ProjectController;
use App\Domain\Project\Controllers\TaskController;
use App\Domain\Time\Controllers\StartTimerController;
use App\Domain\Time\Controllers\StopTimerController;
use App\Domain\Time\Controllers\TimeEntryController;
use App\Domain\User\Controllers\Settings\IntegrationsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    // -- Dashboard ---------------------------------------------------------
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // -- Time entries ------------------------------------------------------
    Route::resource('time-entries', TimeEntryController::class)->except(['show']);
    Route::post('time-entries/{time_entry}/start', StartTimerController::class)->name('time-entries.start');
    Route::post('time-entries/{time_entry}/stop', StopTimerController::class)->name('time-entries.stop');

    // -- Projects & tasks --------------------------------------------------
    Route::resource('projects', ProjectController::class)->except(['show']);
    Route::resource('tasks', TaskController::class)->except(['show']);

    // -- Clients -----------------------------------------------------------
    Route::resource('clients', ClientController::class)->except(['show']);

    // -- Invoices ----------------------------------------------------------
    Route::get('invoices/prepare', [InvoicePrepareController::class, 'create'])->name('invoices.prepare.create');
    Route::post('invoices/prepare', [InvoicePrepareController::class, 'store'])->name('invoices.prepare.store');
    Route::post('invoices/{invoice}/push', InvoicePushController::class)->name('invoices.push');
    Route::post('invoices/{invoice}/sync', InvoiceSyncController::class)->name('invoices.sync');
    Route::resource('invoices', InvoiceController::class)->only(['index', 'show', 'destroy']);

    // -- Integrations (Harvest, Moneybird) ---------------------------------
    Route::get('settings/integrations', [IntegrationsController::class, 'edit'])->name('integrations.edit');
    Route::post('settings/integrations/harvest/import', [HarvestImportController::class, 'store'])->name('integrations.harvest.import');
    Route::get('settings/integrations/harvest/progress', HarvestImportProgressController::class)->name('integrations.harvest.progress');
});

require __DIR__.'/settings.php';
