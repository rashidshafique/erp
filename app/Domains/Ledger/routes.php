<?php

use App\Domains\Ledger\Http\Controllers\AccountController;
use App\Domains\Ledger\Http\Controllers\JournalController;
use App\Domains\Ledger\Http\Controllers\LedgerReportController;
use App\Domains\Ledger\Http\Controllers\LedgerSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('ledger')->name('ledger.')->group(function () {
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{account}', [LedgerReportController::class, 'account'])->name('accounts.show');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    Route::prefix('journals')->name('journals.')->controller(JournalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{journal}', 'show')->name('show');
        Route::post('/{journal}/reverse', 'reverse')->name('reverse');
    });
    Route::get('/trial-balance', [LedgerReportController::class, 'trialBalance'])->name('trial-balance');
    Route::get('/profit-and-loss', [LedgerReportController::class, 'profitAndLoss'])->name('profit-and-loss');
    Route::get('/balance-sheet', [LedgerReportController::class, 'balanceSheet'])->name('balance-sheet');
    Route::get('/general-ledger', [LedgerReportController::class, 'generalLedger'])->name('general-ledger');
    Route::get('/settings', [LedgerSettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [LedgerSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/post-missing', [LedgerSettingsController::class, 'postMissing'])->name('settings.post-missing');
});
