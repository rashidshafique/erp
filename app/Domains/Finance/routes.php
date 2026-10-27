<?php

use App\Domains\Finance\Http\Controllers\BillController;
use App\Domains\Finance\Http\Controllers\FinanceSettingsController;
use App\Domains\Finance\Http\Controllers\InvoiceController;
use App\Domains\Finance\Http\Controllers\ReceiptController;
use App\Domains\Finance\Http\Controllers\PayablesController;
use App\Domains\Finance\Http\Controllers\ReceivablesController;
use App\Domains\Finance\Http\Controllers\SupplierPaymentController;
use App\Domains\Finance\Http\Controllers\TaxRegisterController;
use App\Domains\Finance\Http\Controllers\WithholdingCertificateController;
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
    Route::prefix('withholding-certificates')->name('withholding-certificates.')->controller(WithholdingCertificateController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{certificate}', 'show')->name('show');
        Route::post('/{certificate}/void', 'void')->name('void');
    });
    Route::prefix('bills')->name('bills.')->controller(BillController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{bill}', 'show')->name('show');
        Route::get('/{bill}/edit', 'edit')->name('edit');
        Route::put('/{bill}', 'update')->name('update');
        Route::delete('/{bill}', 'destroy')->name('destroy');
        Route::post('/{bill}/post', 'post')->name('post');
        Route::post('/{bill}/void', 'void')->name('void');
        Route::get('/{bill}/credit', 'credit')->name('credit');
        Route::post('/{bill}/credit', 'storeCredit')->name('credit.store');
    });
    Route::prefix('supplier-payments')->name('supplier-payments.')->controller(SupplierPaymentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{payment}', 'show')->name('show');
        Route::post('/{payment}/allocate', 'allocate')->name('allocate');
    });
    Route::get('/payables', [PayablesController::class, 'index'])->name('payables.index');
    Route::get('/payables/{supplier}', [PayablesController::class, 'statement'])->name('payables.statement');
    Route::get('/receivables', [ReceivablesController::class, 'index'])->name('receivables.index');
    Route::get('/receivables/{customer}', [ReceivablesController::class, 'statement'])->name('receivables.statement');
    Route::get('/tax-register', [TaxRegisterController::class, 'index'])->name('finance.tax-register');
    Route::get('/settings/invoicing', [FinanceSettingsController::class, 'edit'])->name('finance.settings');
    Route::put('/settings/invoicing', [FinanceSettingsController::class, 'update'])->name('finance.settings.update');
    Route::post('/settings/invoicing/tax-rates', [FinanceSettingsController::class, 'storeRate'])->name('finance.tax-rates.store');
    Route::put('/settings/invoicing/tax-rates/{rate}', [FinanceSettingsController::class, 'updateRate'])->name('finance.tax-rates.update');
});
