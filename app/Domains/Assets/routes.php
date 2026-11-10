<?php

use App\Domains\Assets\Http\Controllers\DepreciationRunController;
use App\Domains\Assets\Http\Controllers\FixedAssetController;
use App\Domains\Assets\Models\DepreciationRun;
use App\Domains\Assets\Models\FixedAsset;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('assets')->name('assets.')->group(function () {
    Route::model('asset', FixedAsset::class);
    Route::model('run', DepreciationRun::class);
    Route::controller(DepreciationRunController::class)->group(function () {
        Route::get('/depreciation', 'show')->name('depreciation');
        Route::get('/depreciation/sheet', 'sheet')->name('depreciation.sheet');
        Route::post('/depreciation', 'post')->name('depreciation.post');
        Route::get('/runs/{run}', 'run')->name('runs.show');
        Route::post('/runs/{run}/reverse', 'reverse')->name('runs.reverse');
    });
    Route::controller(FixedAssetController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{asset}', 'show')->name('show');
        Route::get('/{asset}/edit', 'edit')->name('edit');
        Route::put('/{asset}', 'update')->name('update');
        Route::post('/{asset}/in-use', 'putInUse')->name('in-use');
    });
});
