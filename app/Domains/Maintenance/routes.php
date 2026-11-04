<?php

use App\Domains\Maintenance\Http\Controllers\EquipmentMaintenanceController;
use App\Domains\Maintenance\Http\Controllers\GaugeController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::redirect('/settings/calibration', '/settings/rules#calibration');
    Route::prefix('maintenance')->name('equipment-maintenance.')->controller(EquipmentMaintenanceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/equipment', 'storeEquipment')->name('equipment.store');
        Route::post('/meters', 'storeMeter')->name('meters.store');
        Route::post('/meters/{meter}/readings', 'storeReading')->name('meters.readings.store');
        Route::post('/plans', 'storePlan')->name('plans.store');
        Route::post('/work-orders', 'storeWorkOrder')->name('work-orders.store');
        Route::post('/work-orders/{workOrder}/start', 'startWorkOrder')->name('work-orders.start');
        Route::post('/work-orders/{workOrder}/complete', 'completeWorkOrder')->name('work-orders.complete');
        Route::post('/breakdowns', 'storeBreakdown')->name('breakdowns.store');
        Route::post('/breakdowns/{breakdown}/restore', 'restoreBreakdown')->name('breakdowns.restore');
    });
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
