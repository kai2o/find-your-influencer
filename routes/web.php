<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('watchlist.index')
        : redirect()->route('login');
})->name('home');

Route::get('/healthz', HealthController::class)->name('healthz');
Route::post('/webhooks/{provider}', WebhookController::class)->name('webhooks');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', fn () => redirect()->route('watchlist.index'))->name('dashboard');

    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::get('/watchlist/create', [WatchlistController::class, 'create'])->name('watchlist.create');
    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::get('/watchlist/{profile}', [WatchlistController::class, 'show'])->name('watchlist.show');
    Route::post('/watchlist/{profile}/refetch', [WatchlistController::class, 'refetch'])->name('watchlist.refetch');
    Route::delete('/watchlist/{profile}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
