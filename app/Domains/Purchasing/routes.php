<?php

use App\Domains\Purchasing\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('suppliers')->name('suppliers.')->controller(SupplierController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{supplier}', 'show')->name('show');
        Route::get('/{supplier}/edit', 'edit')->name('edit');
        Route::put('/{supplier}', 'update')->name('update');
        Route::post('/{supplier}/evaluations', 'evaluate')->name('evaluate');
    });
});
