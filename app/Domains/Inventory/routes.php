<?php

use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::view('/stock-locations', 'inventory::locations.index')->name('stock-locations.index');
    Route::view('/stock-movements/record', 'inventory::movements-record')->name('stock-movements.record');
    Route::view('/stock-reorder', 'inventory::reorder')->name('stock-reorder.index');
    Route::view('/stock-balances', 'inventory::balances')->name('stock-balances.index');
});
