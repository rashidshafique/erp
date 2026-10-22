<?php

use App\Domains\Licensing\Http\Controllers\FoundrySizeController;
use App\Domains\Licensing\Http\Controllers\ModuleSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/settings/modules', [ModuleSettingsController::class, 'edit'])->name('settings.modules.edit');
    Route::put('/settings/modules', [ModuleSettingsController::class, 'update'])->name('settings.modules');
    Route::get('/settings/size', [FoundrySizeController::class, 'edit'])->name('settings.size.edit');
    Route::put('/settings/size', [FoundrySizeController::class, 'update'])->name('settings.size');
});
