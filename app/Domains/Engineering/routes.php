<?php

use App\Domains\Engineering\Http\Controllers\BomLineController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('bom')->name('bom.')->controller(BomLineController::class)->group(function () {
    Route::post('/revisions/{revision}/lines', 'store')->name('store');
    Route::post('/revisions/{revision}/copy', 'copy')->name('copy');
    Route::put('/lines/{line}', 'update')->name('update');
    Route::delete('/lines/{line}', 'destroy')->name('destroy');
});
