<?php

use App\Domains\Dispatch\Http\Controllers\DispatchController;
use App\Domains\Dispatch\Http\Controllers\FinishedGoodsController;
use App\Domains\Dispatch\Http\Controllers\PackingController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/finished-goods', [FinishedGoodsController::class, 'index'])->name('finished-goods.index');
    Route::prefix('packing')->name('packing.')->controller(PackingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{packing}', 'show')->name('show');
    });
    Route::prefix('dispatches')->name('dispatches.')->controller(DispatchController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{dispatch}', 'show')->name('show');
    });
});
