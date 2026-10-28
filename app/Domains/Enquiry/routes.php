<?php

use App\Domains\Enquiry\Http\Controllers\EnquiryController;
use App\Domains\Enquiry\Http\Controllers\FeasibilityController;
use App\Domains\Enquiry\Http\Controllers\QuotationController;
use App\Domains\Enquiry\Http\Controllers\QuotationSettingsController;
use App\Domains\Enquiry\Http\Controllers\RequestWizardController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('enquiries')->name('enquiries.')->controller(EnquiryController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::get('/wizard', [RequestWizardController::class, 'create'])->name('wizard');
        Route::post('/wizard', [RequestWizardController::class, 'store'])->name('wizard.store');
        Route::post('/', 'store')->name('store');
        Route::get('/{enquiry}', 'show')->name('show');
        Route::put('/{enquiry}', 'update')->name('update');
        Route::post('/{enquiry}/lost', 'lost')->name('lost');
        Route::post('/{enquiry}/items', 'itemStore')->name('items.store');
        Route::put('/{enquiry}/items/{item}', 'itemUpdate')->name('items.update');
        Route::delete('/{enquiry}/items/{item}', 'itemDestroy')->name('items.destroy');
        Route::post('/{enquiry}/items/{item}/feasibility', 'feasibility')->name('items.feasibility');
        Route::post('/{enquiry}/items/{item}/analysis', 'attachAnalysis')->name('items.analysis');
        Route::post('/{enquiry}/quotations', [QuotationController::class, 'store'])->name('quotations.store');
    });

    Route::prefix('feasibility')->name('feasibility.')->controller(FeasibilityController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{item}', 'show')->name('show');
        Route::post('/{item}/departments/{department}', 'sign')->name('sign');
    });

    Route::prefix('quotations')->name('quotations.')->controller(QuotationController::class)->group(function () {
        Route::get('/{quotation}', 'show')->name('show');
        Route::get('/{quotation}/print', 'print')->name('print');
        Route::put('/{quotation}', 'update')->name('update');
        Route::post('/{quotation}/refresh', 'refresh')->name('refresh');
        Route::post('/{quotation}/issue', 'issue')->name('issue');
        Route::post('/{quotation}/reject', 'reject')->name('reject');
        Route::post('/{quotation}/accept', 'accept')->name('accept');
    });

    Route::get('/settings/quotation', [QuotationSettingsController::class, 'edit'])->name('settings.quotation');
    Route::put('/settings/quotation', [QuotationSettingsController::class, 'update'])->name('settings.quotation.update');
});
