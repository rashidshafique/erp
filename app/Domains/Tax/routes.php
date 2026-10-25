<?php

use App\Domains\Tax\Http\Controllers\PartyTaxController;
use App\Domains\Tax\Http\Controllers\TaxSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('settings/tax')->name('tax.')->group(function () {
    Route::controller(TaxSettingsController::class)->group(function () {
        Route::get('/', 'index')->name('settings');
        Route::put('/company', 'updateCompany')->name('company.update');
        Route::post('/defaults', 'loadDefaults')->name('defaults');
        Route::post('/components/{component}/rate', 'changeRate')->name('components.rate');
        Route::post('/rules', 'storeRule')->name('rules.store');
        Route::put('/rules/{rule}', 'updateRule')->name('rules.update');
        Route::delete('/rules/{rule}', 'destroyRule')->name('rules.destroy');
    });
    Route::put('/parties/{party}', [PartyTaxController::class, 'update'])->name('parties.update');
});
