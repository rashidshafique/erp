<?php

use App\Domains\Finance\Http\Controllers\FinanceSettingsController;
use App\Domains\Finance\Http\Controllers\InvoiceController;
use App\Domains\Finance\Http\Controllers\ReceiptController;
use App\Domains\Finance\Http\Controllers\ReceivablesController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('invoices')->name('invoices.')->controller(InvoiceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{invoice}', 'show')->name('show');
        Route::get('/{invoice}/print', 'print')->name('print');
        Route::get('/{invoice}/edit', 'edit')->name('edit');
        Route::put('/{invoice}', 'update')->name('update');
        Route::delete('/{invoice}', 'destroy')->name('destroy');
        Route::post('/{invoice}/issue', 'issue')->name('issue');
        Route::post('/{invoice}/void', 'void')->name('void');
        Route::get('/{invoice}/credit', 'credit')->name('credit');
        Route::post('/{invoice}/credit', 'storeCredit')->name('credit.store');
    });
    Route::prefix('receipts')->name('receipts.')->controller(ReceiptController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{receipt}', 'show')->name('show');
        Route::post('/{receipt}/allocate', 'allocate')->name('allocate');
    });
    Route::get('/receivables', [ReceivablesController::class, 'index'])->name('receivables.index');
    Route::get('/receivables/{customer}', [ReceivablesController::class, 'statement'])->name('receivables.statement');
    Route::get('/settings/invoicing', [FinanceSettingsController::class, 'edit'])->name('finance.settings');
    Route::put('/settings/invoicing', [FinanceSettingsController::class, 'update'])->name('finance.settings.update');
    Route::post('/settings/invoicing/tax-rates', [FinanceSettingsController::class, 'storeRate'])->name('finance.tax-rates.store');
    Route::put('/settings/invoicing/tax-rates/{rate}', [FinanceSettingsController::class, 'updateRate'])->name('finance.tax-rates.update');
});
