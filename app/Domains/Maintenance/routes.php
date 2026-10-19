<?php

use App\Domains\Maintenance\Http\Controllers\CalibrationRulesController;
use App\Domains\Maintenance\Http\Controllers\GaugeController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/settings/calibration', [CalibrationRulesController::class, 'edit'])->name('settings.calibration.edit');
    Route::put('/settings/calibration', [CalibrationRulesController::class, 'update'])->name('settings.calibration');
    Route::prefix('gauges')->name('gauges.')->controller(GaugeController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{gauge}', 'show')->name('show');
        Route::get('/{gauge}/edit', 'edit')->name('edit');
        Route::put('/{gauge}', 'update')->name('update');
        Route::post('/{gauge}/calibrations', 'calibrate')->name('calibrate');
    });
});
