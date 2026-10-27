<?php

use App\Domains\Sales\Http\Controllers\PriceListController;
use App\Domains\Sales\Http\Controllers\SalesOrderController;
use App\Http\Controllers\OrderTraceController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('price-lists')->name('price-lists.')->controller(PriceListController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/lookup', 'lookup')->name('lookup');
        Route::put('/settings', 'settings')->name('settings');
        Route::put('/customers/{customer}', 'assign')->name('assign');
        Route::get('/{priceList}', 'show')->name('show');
        Route::put('/{priceList}', 'update')->name('update');
        Route::delete('/{priceList}', 'destroy')->name('destroy');
        Route::put('/{priceList}/items', 'items')->name('items');
        Route::post('/{priceList}/copy', 'copy')->name('copy');
    });
    Route::prefix('sales-orders')->name('sales-orders.')->controller(SalesOrderController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{salesOrder}', 'show')->name('show');
        Route::get('/{salesOrder}/edit', 'edit')->name('edit');
        Route::put('/{salesOrder}', 'update')->name('update');
        Route::post('/{salesOrder}/status', 'changeStatus')->name('status');
        Route::post('/{salesOrder}/review', 'review')->name('review');
        Route::post('/{salesOrder}/review/acknowledge', 'acknowledge')->name('review.acknowledge');
        Route::post('/{salesOrder}/review/sign', 'sign')->name('review.sign');
        Route::post('/{salesOrder}/lines/{line}/production-order', 'productionOrder')->name('lines.production-order');
        Route::post('/{salesOrder}/lines/{line}/deliveries', 'addDelivery')->name('lines.deliveries.store');
        Route::delete('/{salesOrder}/lines/{line}/deliveries/{delivery}', 'removeDelivery')->name('lines.deliveries.destroy');
        Route::put('/{salesOrder}/lines/{line}/forecast', 'forecast')->name('lines.forecast');
        Route::get('/{salesOrder}/trace', [OrderTraceController::class, 'show'])->name('trace');
    });
});
