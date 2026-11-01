<?php

use App\Domains\Ledger\Http\Controllers\AccountController;
use App\Domains\Ledger\Http\Controllers\BankController;
use App\Domains\Ledger\Http\Controllers\JournalController;
use App\Domains\Ledger\Http\Controllers\LedgerReportController;
use App\Domains\Ledger\Http\Controllers\LedgerSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('ledger')->name('ledger.')->group(function () {
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/export', [AccountController::class, 'export'])->name('accounts.export');
    Route::get('/sections', [AccountController::class, 'sections'])->name('sections.index');
    Route::get('/accounts/{account}', [LedgerReportController::class, 'account'])->name('accounts.show');
    Route::prefix('journals')->name('journals.')->controller(JournalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/export', 'export')->name('export');
        Route::get('/{journal}', 'show')->name('show');
        Route::get('/{journal}/print', 'print')->name('print');
        Route::post('/{journal}/reverse', 'reverse')->name('reverse');
    });
    Route::get('/vouchers/{voucher}', [JournalController::class, 'edit'])->name('vouchers.edit');
    Route::get('/vouchers/{voucher}/print', [JournalController::class, 'printDraft'])->name('vouchers.print');
    Route::controller(BankController::class)->group(function () {
        Route::get('/banks', 'index')->name('banks.index');
        Route::post('/banks', 'store')->name('banks.store');
        Route::get('/banks/{bank}', 'show')->name('banks.show');
        Route::put('/banks/{bank}', 'update')->name('banks.update');
        Route::post('/banks/{bank}/statements', 'startStatement')->name('banks.statements.store');
        Route::get('/statements/{reconciliation}', 'statement')->name('reconciliations.show');
        Route::put('/statements/{reconciliation}', 'tick')->name('reconciliations.update');
        Route::post('/statements/{reconciliation}/line', 'statementLine')->name('reconciliations.line');
        Route::post('/statements/{reconciliation}/reopen', 'reopen')->name('reconciliations.reopen');
        Route::delete('/statements/{reconciliation}', 'discard')->name('reconciliations.destroy');
    });
    Route::get('/opening-balances', fn () => view('ledger::opening-balances'))->name('opening-balances');
    Route::get('/trial-balance',[LedgerReportController::class, 'trialBalance'])->name('trial-balance');
    Route::get('/profit-and-loss', [LedgerReportController::class, 'profitAndLoss'])->name('profit-and-loss');
    Route::get('/balance-sheet', [LedgerReportController::class, 'balanceSheet'])->name('balance-sheet');
    Route::get('/general-ledger', [LedgerReportController::class, 'generalLedger'])->name('general-ledger');
    Route::get('/settings', [LedgerSettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [LedgerSettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/closing', [LedgerSettingsController::class, 'closing'])->name('settings.closing');
    Route::post('/settings/post-missing', [LedgerSettingsController::class, 'postMissing'])->name('settings.post-missing');
});
