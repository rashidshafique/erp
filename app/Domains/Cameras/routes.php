<?php

use App\Domains\Cameras\Http\Controllers\CameraSetupController;
use App\Domains\Cameras\Http\Controllers\CameraViewController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('cameras')->name('cameras.')->group(function () {
    Route::get('/', [CameraViewController::class, 'index'])->name('index');

    Route::get('/setup', [CameraSetupController::class, 'index'])->name('setup');
    Route::post('/setup/dvrs', [CameraSetupController::class, 'storeDvr'])->name('dvrs.store');
    Route::put('/setup/dvrs/{dvr}', [CameraSetupController::class, 'updateDvr'])->name('dvrs.update');
    Route::delete('/setup/dvrs/{dvr}', [CameraSetupController::class, 'destroyDvr'])->name('dvrs.destroy');
    Route::post('/setup/dvrs/{dvr}/test', [CameraSetupController::class, 'testDvr'])->name('dvrs.test');
    Route::post('/setup/dvrs/{dvr}/channels', [CameraSetupController::class, 'storeChannels'])->name('channels.store');
    Route::put('/setup/cameras/{camera}', [CameraSetupController::class, 'updateCamera'])->name('update');
    Route::delete('/setup/cameras/{camera}', [CameraSetupController::class, 'destroyCamera'])->name('destroy');

    Route::get('/{camera}/snapshot', [CameraViewController::class, 'snapshot'])->name('snapshot');
    Route::get('/{camera}/stream', [CameraViewController::class, 'stream'])->name('stream');
});
