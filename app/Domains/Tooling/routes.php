<?php

use App\Domains\Tooling\Http\Controllers\CoreBoxController;
use App\Domains\Tooling\Http\Controllers\CoreController;
use App\Domains\Tooling\Http\Controllers\CoreLotController;
use App\Domains\Tooling\Http\Controllers\CoreRequirementController;
use App\Domains\Tooling\Http\Controllers\ToolController;
use App\Domains\Tooling\Http\Controllers\ToolingLifeDashboardController;
use App\Domains\Tooling\Http\Controllers\ToolingLifeLimitController;
use App\Domains\Tooling\Http\Controllers\ToolingMaintenanceDashboardController;
use App\Domains\Tooling\Http\Controllers\ToolingMaintenanceHistoryController;
use App\Domains\Tooling\Http\Controllers\ToolingMaintenanceRecordController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get(
        '/tooling-maintenance/dashboard',
        ToolingMaintenanceDashboardController::class
    )->name('tooling-maintenance.dashboard');
    Route::get(
        '/tooling-maintenance',
        [ToolingMaintenanceRecordController::class, 'index']
    )->name('tooling-maintenance.index');
    Route::get(
        '/tooling-maintenance/create',
        [ToolingMaintenanceRecordController::class, 'create']
    )->name('tooling-maintenance.create');
    Route::post(
        '/tooling-maintenance',
        [ToolingMaintenanceRecordController::class, 'store']
    )->name('tooling-maintenance.store');
    Route::get(
        '/tooling-maintenance/{toolingMaintenance}',
        [ToolingMaintenanceRecordController::class, 'show']
    )->name('tooling-maintenance.show');
    Route::get(
        '/tooling-maintenance/{toolingMaintenance}/edit',
        [ToolingMaintenanceRecordController::class, 'edit']
    )->name('tooling-maintenance.edit');
    Route::put(
        '/tooling-maintenance/{toolingMaintenance}',
        [ToolingMaintenanceRecordController::class, 'update']
    )->name('tooling-maintenance.update');
    Route::get(
        '/tooling-maintenance/{toolingMaintenance}/history',
        ToolingMaintenanceHistoryController::class
    )->name('tooling-maintenance.history');
    Route::get(
        '/tooling-life',
        ToolingLifeDashboardController::class
    )->name('tooling-life.dashboard');
    Route::get(
        '/tooling-life/create',
        [ToolingLifeLimitController::class, 'create']
    )->name('tooling-life.create');
    Route::post(
        '/tooling-life',
        [ToolingLifeLimitController::class, 'store']
    )->name('tooling-life.store');
    Route::get(
        '/tooling-life/{toolingLifeLimit}/edit',
        [ToolingLifeLimitController::class, 'edit']
    )->name('tooling-life.edit');
    Route::put(
        '/tooling-life/{toolingLifeLimit}',
        [ToolingLifeLimitController::class, 'update']
    )->name('tooling-life.update');

    Route::prefix('cores')->name('cores.')->controller(CoreController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{core}', 'show')->name('show')->withTrashed();
        Route::get('/{core}/edit', 'edit')->name('edit');
        Route::put('/{core}', 'update')->name('update');
        Route::delete('/{core}', 'archive')->name('archive');
        Route::post('/{core}/restore', 'restore')->name('restore')->withTrashed();
    });
    Route::prefix('core-boxes')->name('core-boxes.')->controller(CoreBoxController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{coreBox}', 'show')->name('show')->withTrashed();
        Route::get('/{coreBox}/edit', 'edit')->name('edit');
        Route::put('/{coreBox}', 'update')->name('update');
        Route::put('/{coreBox}/cavities', 'updateCavities')->name('cavities');
        Route::delete('/{coreBox}', 'archive')->name('archive');
        Route::post('/{coreBox}/restore', 'restore')->name('restore')->withTrashed();
    });

    Route::prefix('core-lots')->name('core-lots.')->controller(CoreLotController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{coreLot}', 'show')->name('show');
        Route::put('/{coreLot}/location', 'location')->name('location');
        Route::put('/{coreLot}/check', 'check')->name('check');
        Route::post('/{coreLot}/take', 'take')->name('take');
    });
    Route::post('/moulding-runs/{mouldingRun}/cores', [CoreLotController::class, 'issueToRun'])->name('core-lots.issue-to-run');
    Route::get('/settings/cores', [CoreLotController::class, 'editRules'])->name('settings.cores.edit');
    Route::put('/settings/cores', [CoreLotController::class, 'updateRules'])->name('settings.cores');

    Route::post('/core-requirements', [CoreRequirementController::class, 'store'])->name('core-requirements.store');
    Route::delete('/core-requirements/{requirement}', [CoreRequirementController::class, 'destroy'])->name('core-requirements.destroy');

    Route::get('/tools/{type}/{id}', [ToolController::class, 'show'])->where('type', '[a-z_]+')->name('tools.show');
    Route::put('/tools/{type}/{id}/mark', [ToolController::class, 'updateMark'])->where('type', '[a-z_]+')->name('tools.mark');
});
