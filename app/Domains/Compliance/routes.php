<?php

use App\Domains\Compliance\Http\Controllers\CustomerRequirementController;
use App\Domains\Compliance\Http\Controllers\RequirementProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('settings/requirements')->name('requirements.')->controller(RequirementProfileController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{profile}/edit', 'edit')->name('edit');
        Route::put('/{profile}', 'update')->name('update');
    });
    Route::get('/customers/{customer}/requirements', [CustomerRequirementController::class, 'edit'])->name('customer-requirements.edit');
    Route::put('/customers/{customer}/requirements', [CustomerRequirementController::class, 'update'])->name('customer-requirements.update');
});
