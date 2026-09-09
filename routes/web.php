<?php

use App\Domain\Client\Controllers\ClientController;
use App\Domain\Dashboard\Controllers\DashboardController;
use App\Domain\Invoice\Controllers\InvoiceController;
use App\Domain\Project\Controllers\ProjectController;
use App\Domain\Project\Controllers\TaskController;
use App\Domain\Time\Controllers\TimeEntryController;
use App\Domain\User\Controllers\Settings\IntegrationsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('time-entries', TimeEntryController::class)->except(['show']);
    Route::resource('projects', ProjectController::class)->except(['show']);
    Route::resource('clients', ClientController::class)->except(['show']);
    Route::resource('tasks', TaskController::class)->except(['show']);
    Route::resource('invoices', InvoiceController::class)->only(['index', 'show', 'destroy']);

    Route::get('settings/integrations', [IntegrationsController::class, 'edit'])->name('integrations.edit');
});

require __DIR__.'/settings.php';
