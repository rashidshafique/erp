<?php

use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::view('/stock-locations', 'inventory::locations.index')->name('stock-locations.index');
});
