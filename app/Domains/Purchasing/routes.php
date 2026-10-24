<?php

use App\Domains\Purchasing\Http\Controllers\IncomingController;
use App\Domains\Purchasing\Http\Controllers\PurchaseOrderController;
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

    Route::prefix('incoming')->name('incoming.')->controller(IncomingController::class)->group(function () {
        Route::get('/settings', 'editSettings')->name('settings.edit');
        Route::put('/settings', 'updateSettings')->name('settings.update');
        Route::get('/materials/{material}/plan', 'editPlan')->name('plan.edit');
        Route::put('/materials/{material}/plan', 'updatePlan')->name('plan.update');
        Route::get('/lots/{lot}', 'showLot')->name('lots.show');
        Route::put('/lots/{lot}/certificate-type', 'updateCertificateType')->name('lots.certificate-type');
        Route::post('/lots/{lot}/inspections', 'storeInspection')->name('inspections.store');
        Route::post('/lots/{lot}/accept', 'acceptLot')->name('lots.accept');
    });

    Route::prefix('purchase-orders')->name('purchase-orders.')->controller(PurchaseOrderController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/settings', 'editSettings')->name('settings.edit');
        Route::put('/settings', 'updateSettings')->name('settings.update');
        Route::get('/{purchaseOrder}', 'show')->name('show');
        Route::get('/{purchaseOrder}/print', 'print')->name('print');
        Route::get('/{purchaseOrder}/edit', 'edit')->name('edit');
        Route::put('/{purchaseOrder}', 'update')->name('update');
        Route::delete('/{purchaseOrder}', 'destroy')->name('destroy');
        Route::post('/{purchaseOrder}/issue', 'issue')->name('issue');
        Route::post('/{purchaseOrder}/receive', 'receive')->name('receive');
        Route::post('/{purchaseOrder}/close', 'close')->name('close');
    });
});
