<?php

use App\Domains\QuickFlow\Http\Controllers\FloorEntryController;
use App\Domains\QuickFlow\Http\Controllers\NextHeatController;
use App\Domains\QuickFlow\Http\Controllers\QuickOrderController;
use App\Domains\QuickFlow\Http\Controllers\TodayController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('quick')->name('quick.')->group(function () {
    Route::get('/today', [TodayController::class, 'index'])->name('today');
    Route::get('/orders/new', [QuickOrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [QuickOrderController::class, 'store'])->name('orders.store');
    Route::get('/floor', [FloorEntryController::class, 'index'])->name('floor.index');
    Route::post('/floor/made', [FloorEntryController::class, 'made'])->name('floor.made');
    Route::post('/floor/poured', [FloorEntryController::class, 'poured'])->name('floor.poured');
    Route::post('/floor/entries/{entry}/link', [FloorEntryController::class, 'link'])->name('floor.link');
    Route::get('/next-heat', [NextHeatController::class, 'index'])->name('heat.index');
    Route::post('/next-heat/start', [NextHeatController::class, 'start'])->name('heat.start');
});
