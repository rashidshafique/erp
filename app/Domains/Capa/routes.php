<?php

use App\Domains\Capa\Http\Controllers\CapaController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('corrective-actions')->name('capa.')->controller(CapaController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/from-ncr/{ncr}', 'fromNcr')->name('from-ncr');
        Route::get('/{capa}', 'show')->name('show');
        Route::put('/{capa}', 'update')->name('update');
        Route::post('/{capa}/complete', 'complete')->name('complete');
        Route::post('/{capa}/verify', 'verify')->name('verify');
        Route::post('/{capa}/cancel', 'cancel')->name('cancel');
    });
});
