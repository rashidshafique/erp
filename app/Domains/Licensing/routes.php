<?php

use App\Domains\Licensing\Http\Controllers\ModuleSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/settings/modules', [ModuleSettingsController::class, 'edit'])->name('settings.modules.edit');
    Route::put('/settings/modules', [ModuleSettingsController::class, 'update'])->name('settings.modules');
});
