<?php

use App\Domain\Desktop\Controllers\DesktopIdleTimeController;
use App\Domain\Desktop\Controllers\DesktopStateController;
use App\Domain\Desktop\Controllers\DesktopTimerController;
use App\Domain\Desktop\Controllers\DesktopTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Kingtime for Mac
|--------------------------------------------------------------------------
|
| The menu bar app signs in once with the user's email and password (plus
| the two-factor code when the account has one) and keeps the Sanctum token
| it gets back. Every other call carries that token as a bearer token.
|
*/
Route::prefix('desktop')->name('desktop.')->group(function (): void {
    Route::post('tokens', [DesktopTokenController::class, 'store'])
        ->middleware('throttle:login')
        ->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('tokens/current', [DesktopTokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('state', DesktopStateController::class)->name('state');
        Route::post('timer', [DesktopTimerController::class, 'store'])->name('timer.store');
        Route::delete('timer', [DesktopTimerController::class, 'destroy'])->name('timer.destroy');
        Route::post('timer/idle', DesktopIdleTimeController::class)->name('timer.idle');
    });
});
