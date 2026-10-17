<?php
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialLotController;
use App\Http\Controllers\ChargeBatchController;
use App\Http\Controllers\HeatReadingController;
use App\Http\Controllers\FurnaceController;
use App\Http\Controllers\PourController;
use App\Http\Controllers\PatternController;
use App\Http\Controllers\ErpDashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\MouldingRunController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ToolingLifeLimitController;
use App\Http\Controllers\ToolingLifeDashboardController;
use App\Http\Controllers\ToolingMaintenanceHistoryController;
use App\Http\Controllers\ToolingMaintenanceAttachmentController;
use App\Http\Controllers\ToolingMaintenanceDashboardController;
use App\Http\Controllers\ToolingMaintenanceRecordController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockReconciliationController;

Route::get('/stock-report', [StockReportController::class, 'index'])
    ->name('stock-report.index');

Route::get('/stock-movements', [StockMovementController::class, 'index'])
    ->name('stock-movements.index');
Route::get('/stock-reconciliation', [StockReconciliationController::class, 'index'])
    ->name('stock-reconciliation.index');
    
Route::get('/', ErpDashboardController::class)->name('dashboard');
Route::prefix('moulding-runs')
    ->name('moulding-runs.')
    ->controller(MouldingRunController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/bulk', [\App\Http\Controllers\MouldingRunBulkController::class, 'edit'])->name('bulk');
        Route::post('/bulk', [\App\Http\Controllers\MouldingRunBulkController::class, 'save'])->name('bulk.save');
        Route::get('/{mouldingRun}', 'show')->name('show');
        Route::put('/{mouldingRun}/crew', 'updateCrew')->name('crew');
    });

Route::prefix('patterns')->name('patterns.')->controller(PatternController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{pattern}', 'show')->name('show');
    Route::put('/{pattern}/sand', 'updateSand')->name('sand');
    Route::post('/{pattern}/revisions', 'storeRevision')->name('revisions.store');
    Route::post('/{pattern}/cavities', [\App\Http\Controllers\PatternCavityController::class, 'add'])->name('cavities.add');
    Route::post('/{pattern}/revisions/{revision}/cavities', [\App\Http\Controllers\PatternCavityController::class, 'store'])->name('cavities.store');
    Route::put('/{pattern}/revisions/{revision}/cavities/{cavity}', [\App\Http\Controllers\PatternCavityController::class, 'update'])->name('cavities.update');
    Route::put('/{pattern}/revisions/{revision}/cavities/{cavity}/reviewed', [\App\Http\Controllers\PatternCavityController::class, 'review'])->name('cavities.review');
    Route::delete('/{pattern}/revisions/{revision}/cavities/{cavity}', [\App\Http\Controllers\PatternCavityController::class, 'destroy'])->name('cavities.destroy');
});

    Route::prefix('casting-analyses')
    ->name('casting-analyses.')
    ->controller(\App\Http\Controllers\CastingAnalysisController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{analysis}', 'show')->name('show');
        Route::get('/{analysis}/field', 'field')->name('field');
        Route::post('/{analysis}/rerun', 'rerun')->name('rerun');
        Route::delete('/{analysis}', 'destroy')->name('destroy');
    });

    Route::prefix('imports')->name('imports.')->controller(\App\Http\Controllers\DataImportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/new', 'create')->name('create');
        Route::get('/template/{entity}', 'template')->name('template');
        Route::post('/', 'store')->name('store');
        Route::delete('/templates/{template}', 'deleteTemplate')->name('templates.destroy');
        Route::get('/{batch}', 'show')->name('show');
        Route::get('/{batch}/map', 'map')->name('map');
        Route::post('/{batch}/map', 'saveMap')->name('map.save');
        Route::get('/{batch}/preview', 'preview')->name('preview');
        Route::post('/{batch}/values', 'values')->name('values');
        Route::post('/{batch}/commit', 'commit')->name('commit');
        Route::get('/{batch}/progress', 'progress')->name('progress');
        Route::get('/{batch}/report', 'report')->name('report');
        Route::delete('/{batch}', 'destroy')->name('destroy');
    });

    Route::prefix('enquiries')->name('enquiries.')->controller(\App\Http\Controllers\EnquiryController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::get('/wizard', [\App\Http\Controllers\RequestWizardController::class, 'create'])->name('wizard');
        Route::post('/wizard', [\App\Http\Controllers\RequestWizardController::class, 'store'])->name('wizard.store');
        Route::post('/', 'store')->name('store');
        Route::get('/{enquiry}', 'show')->name('show');
        Route::put('/{enquiry}', 'update')->name('update');
        Route::post('/{enquiry}/lost', 'lost')->name('lost');
        Route::post('/{enquiry}/items', 'itemStore')->name('items.store');
        Route::put('/{enquiry}/items/{item}', 'itemUpdate')->name('items.update');
        Route::delete('/{enquiry}/items/{item}', 'itemDestroy')->name('items.destroy');
        Route::post('/{enquiry}/items/{item}/feasibility', 'feasibility')->name('items.feasibility');
        Route::post('/{enquiry}/items/{item}/analysis', 'attachAnalysis')->name('items.analysis');
        Route::post('/{enquiry}/quotations', [\App\Http\Controllers\QuotationController::class, 'store'])->name('quotations.store');
    });

    Route::prefix('quotations')->name('quotations.')->controller(\App\Http\Controllers\QuotationController::class)->group(function () {
        Route::get('/{quotation}', 'show')->name('show');
        Route::get('/{quotation}/print', 'print')->name('print');
        Route::put('/{quotation}', 'update')->name('update');
        Route::post('/{quotation}/refresh', 'refresh')->name('refresh');
        Route::post('/{quotation}/issue', 'issue')->name('issue');
        Route::post('/{quotation}/reject', 'reject')->name('reject');
        Route::post('/{quotation}/accept', 'accept')->name('accept');
    });

    Route::get('/settings/quotation', [\App\Http\Controllers\QuotationSettingsController::class, 'edit'])->name('settings.quotation');
    Route::put('/settings/quotation', [\App\Http\Controllers\QuotationSettingsController::class, 'update'])->name('settings.quotation.update');

    Route::prefix('products')
    ->name('products.')
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{product}/edit', 'edit')->name('edit');
        Route::put('/{product}', 'update')->name('update');
        Route::post('/{product}/revisions', 'storeRevision')
            ->name('revisions.store');
        Route::get('/{product}', 'show')->name('show');
    });

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

Route::post(
    '/tooling-maintenance/{toolingMaintenance}/attachments',
    [ToolingMaintenanceAttachmentController::class, 'store']
)->name('tooling-maintenance.attachments.store');

Route::delete(
    '/tooling-maintenance/{toolingMaintenance}/attachments/{attachment}',
    [ToolingMaintenanceAttachmentController::class, 'destroy']
)->name('tooling-maintenance.attachments.destroy');

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

Route::prefix('production-orders')
    ->name('production-orders.')
    ->controller(ProductionOrderController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');

        Route::post(
            '/{productionOrder}/release',
            'release'
        )->name('release');
    });

Route::prefix('pours')
    ->name('pours.')
    ->controller(PourController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{pour}', 'show')->name('show');
    });

Route::prefix('furnaces')
    ->name('furnaces.')
    ->controller(FurnaceController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
    });

Route::post(
    '/heats/{heat}/charge-entries',
    [\App\Http\Controllers\HeatMaterialConsumptionController::class, 'store']
)->name('heats.charge-entries.store');

Route::prefix('heats')
    ->name('heats.')
    ->controller(HeatReadingController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{heat}', 'show')->name('show');

        Route::post('/{heat}/power-readings', 'storePowerReading')
            ->name('power-readings.store');

        Route::post('/{heat}/process-readings', 'storeProcessReading')
            ->name('process-readings.store');
    });


Route::prefix('materials')
    ->name('materials.')
    ->controller(MaterialController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{material}', 'show')->name('show');
        Route::get('/{material}/edit', 'edit')->name('edit');
        Route::put('/{material}', 'update')->name('update');
        Route::get('/{material}/lots/create', [MaterialLotController::class, 'create'])
            ->name('lots.create');
        Route::post('/{material}/lots', [MaterialLotController::class, 'store'])
            ->name('lots.store');
        Route::post('/{material}/lots/{lot}/post-receipt',[MaterialLotController::class, 'postReceipt'])->name('lots.post-receipt');
    });

    Route::get('/stock-report', [StockReportController::class, 'index'])
    ->name('stock-report.index');

Route::prefix('charge-recipes')
    ->name('charge-batches.')
    ->controller(ChargeBatchController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{chargeBatch}', 'show')->name('show');
    });
Route::post(
    '/heats/{heat}/post-charge',
    [\App\Http\Controllers\HeatReadingController::class, 'postCharge']
)->name('heats.charge-postings.store');


Route::prefix('settings')
    ->name('settings.')
    ->controller(AppSettingController::class)
    ->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
    });

// ---- Orders, contract review, planning, thermal analysis, quality gates ----
Route::prefix('customers')->name('customers.')->controller(\App\Http\Controllers\CustomerController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
});

Route::prefix('sales-orders')->name('sales-orders.')->controller(\App\Http\Controllers\SalesOrderController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{salesOrder}', 'show')->name('show');
    Route::post('/{salesOrder}/review', 'review')->name('review');
    Route::post('/{salesOrder}/review/acknowledge', 'acknowledge')->name('review.acknowledge');
    Route::post('/{salesOrder}/review/sign', 'sign')->name('review.sign');
    Route::post('/{salesOrder}/lines/{line}/production-order', 'productionOrder')->name('lines.production-order');
    Route::get('/{salesOrder}/trace', [\App\Http\Controllers\OrderTraceController::class, 'show'])->name('trace');
});

Route::prefix('iron-grades')->name('iron-grades.')->controller(\App\Http\Controllers\IronGradeController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{ironGrade}', 'show')->name('show');
    Route::put('/{ironGrade}', 'update')->name('update');
    Route::put('/{ironGrade}/thermal-window', 'updateWindow')->name('window');
    Route::post('/{ironGrade}/furnace-capability', 'setCapability')->name('capability');
});

Route::prefix('thermal')->name('thermal.')->controller(\App\Http\Controllers\ThermalTestController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/sample-curve.csv', 'sample')->name('sample');
    Route::post('/calibrate', 'calibrate')->name('calibrate');
    Route::get('/{test}', 'show')->name('show');
});

Route::prefix('heat-plan')->name('heat-plan.')->controller(\App\Http\Controllers\HeatPlanController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/rebuild', 'rebuild')->name('rebuild');
    Route::post('/{plannedHeat}/furnace', 'assign')->name('assign');
    Route::post('/{plannedHeat}/release', 'release')->name('release');
});

Route::post('/pours/{pour}/start', [PourController::class, 'start'])->name('pours.start');
Route::post('/pours/{pour}/complete', [PourController::class, 'complete'])->name('pours.complete');

// ---- Shop floor: chemistry, pour assemblies, casting lots, inspection, packing, dispatch ----
Route::post('/heats/{heat}/chemistry', [\App\Http\Controllers\HeatChemistryController::class, 'store'])->name('heats.chemistry.store');
Route::post('/heats/{heat}/chemistry/{reading}/verify', [\App\Http\Controllers\HeatChemistryController::class, 'verify'])->name('heats.chemistry.verify');
Route::post('/moulding-runs/{mouldingRun}/assemblies', [\App\Http\Controllers\MouldingRunController::class, 'assemble'])->name('moulding-runs.assemble');
Route::post('/pours/{pour}/assemblies', [\App\Http\Controllers\PourController::class, 'attachAssemblies'])->name('pours.assemblies.attach');
Route::delete('/pours/{pour}/assemblies/{row}', [\App\Http\Controllers\PourController::class, 'detachAssembly'])->name('pours.assemblies.detach');

// ---- Casting lots, inspections, NCRs, packing, dispatch, certificate ----
Route::prefix('lots')->name('lots.')->controller(\App\Http\Controllers\LotController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/queue', [\App\Http\Controllers\LotQueueController::class, 'queue'])->name('queue');
    Route::get('/{lot}', 'show')->name('show');
    Route::post('/{lot}/stages', 'storeStage')->name('stages.store');
    Route::post('/{lot}/hold', 'hold')->name('hold');
    Route::post('/{lot}/release', 'release')->name('release');
    Route::get('/{lot}/certificate', [\App\Http\Controllers\CertificateController::class, 'show'])->name('certificate');
    Route::get('/{lot}/inspections/create', [\App\Http\Controllers\InspectionController::class, 'create'])->name('inspections.create');
    Route::post('/{lot}/inspections', [\App\Http\Controllers\InspectionController::class, 'store'])->name('inspections.store');
});
Route::prefix('inspections')->name('inspections.')->controller(\App\Http\Controllers\InspectionController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{inspection}', 'show')->name('show');
    Route::post('/{inspection}/results', 'saveResults')->name('results.save');
    Route::post('/{inspection}/results/add', 'addResult')->name('results.add');
    Route::post('/{inspection}/complete', 'complete')->name('complete');
});
Route::prefix('ncrs')->name('ncrs.')->controller(\App\Http\Controllers\NcrController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{ncr}', 'show')->name('show');
    Route::put('/{ncr}', 'update')->name('update');
    Route::post('/{ncr}/close', 'close')->name('close');
});
Route::prefix('packing')->name('packing.')->controller(\App\Http\Controllers\PackingController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{packing}', 'show')->name('show');
});
Route::prefix('dispatches')->name('dispatches.')->controller(\App\Http\Controllers\DispatchController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{dispatch}', 'show')->name('show');
});

// ---- Moulding teams, stations, daily work lists, mould readiness ----
Route::post('/moulding-runs/{mouldingRun}/output', [\App\Http\Controllers\MouldingRunController::class, 'recordOutput'])->name('moulding-runs.output');
Route::controller(\App\Http\Controllers\MouldingResourceController::class)->prefix('moulding-teams')->name('moulding-teams.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/teams', 'storeTeam')->name('teams.store');
    Route::put('/teams/{team}', 'updateTeam')->name('teams.update');
    Route::post('/stations', 'storeStation')->name('stations.store');
    Route::put('/stations/{station}', 'updateStation')->name('stations.update');
});
Route::prefix('sand-systems')->name('sand-systems.')->controller(\App\Http\Controllers\SandSystemController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{sandSystem}', 'update')->name('update');
    Route::delete('/{sandSystem}', 'destroy')->name('destroy');
    Route::post('/sands', [\App\Http\Controllers\BaseSandController::class, 'store'])->name('base.store');
    Route::put('/sands/{baseSand}', [\App\Http\Controllers\BaseSandController::class, 'update'])->name('base.update');
    Route::delete('/sands/{baseSand}', [\App\Http\Controllers\BaseSandController::class, 'destroy'])->name('base.destroy');
});
Route::prefix('work-lists')->name('work-lists.')->controller(\App\Http\Controllers\WorkListController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('/{list}', 'show')->name('show');
    Route::get('/{list}/print', 'print')->name('print');
    Route::post('/{list}/items', 'addItem')->name('items.add');
    Route::post('/{list}/rows', 'addRows')->name('rows.add');
    Route::post('/{list}/stations/{station}/change', 'changeStation')->name('stations.change');
    Route::delete('/{list}/items/{item}', 'removeItem')->name('items.remove');
    Route::post('/{list}/issue', 'issue')->name('issue');
    Route::post('/{list}/items/{item}/start', 'startRun')->name('items.start');
    Route::post('/{list}/items/{item}/link', 'linkItem')->name('items.link');
    Route::post('/{list}/stations/{station}/start', 'startStation')->name('stations.start');
    Route::post('/{list}/cancel', 'cancel')->name('cancel');
});
Route::get('/mould-readiness', [\App\Http\Controllers\MouldReadinessController::class, 'index'])->name('mould-readiness.index');

// ---- Custom fields ----
Route::get('/settings/custom-fields', [\App\Http\Controllers\CustomFieldController::class, 'index'])->name('custom-fields.index');
Route::post('/settings/custom-fields', [\App\Http\Controllers\CustomFieldController::class, 'store'])->name('custom-fields.store');
Route::put('/settings/custom-fields/{field}', [\App\Http\Controllers\CustomFieldController::class, 'update'])->name('custom-fields.update');
Route::post('/custom-fields/{entity}/{id}', [\App\Http\Controllers\CustomFieldController::class, 'saveValues'])->name('custom-fields.values');

// ---- Measurements: material tests and limits ----
Route::post('/heats/{heat}/tests/{kind}', [\App\Http\Controllers\MaterialTestController::class, 'store'])->name('heats.tests.store');
Route::post('/limits', [\App\Http\Controllers\CharacteristicLimitController::class, 'store'])->name('limits.store');
Route::put('/limits/{limit}', [\App\Http\Controllers\CharacteristicLimitController::class, 'update'])->name('limits.update');
Route::delete('/limits/{limit}', [\App\Http\Controllers\CharacteristicLimitController::class, 'destroy'])->name('limits.destroy');

// ---- Sand lab ----
Route::get('/sand-tests', [\App\Http\Controllers\SandTestController::class, 'index'])->name('sand-tests.index');
Route::post('/sand-tests', [\App\Http\Controllers\SandTestController::class, 'store'])->name('sand-tests.store');

// ---- Melt treatment and process controls ----
Route::post('/heats/{heat}/treatments', [\App\Http\Controllers\HeatTreatmentController::class, 'store'])->name('heats.treatments.store');
Route::delete('/heats/{heat}/treatments/{treatment}', [\App\Http\Controllers\HeatTreatmentController::class, 'destroy'])->name('heats.treatments.destroy');
Route::put('/settings/process-controls', [\App\Http\Controllers\ProcessControlController::class, 'update'])->name('process-controls.update');
Route::put('/pattern-revisions/{revision}/cooling', [\App\Http\Controllers\ProcessControlController::class, 'cooling'])->name('pattern-revisions.cooling');

// ---- Reports and defect list ----
Route::get('/reports/yield', [\App\Http\Controllers\YieldReportController::class, 'index'])->name('reports.yield');
Route::get('/defects', [\App\Http\Controllers\DefectController::class, 'index'])->name('defects.index');
Route::post('/defects', [\App\Http\Controllers\DefectController::class, 'store'])->name('defects.store');
Route::put('/defects/{defect}', [\App\Http\Controllers\DefectController::class, 'update'])->name('defects.update');
Route::get('/finished-goods', [\App\Http\Controllers\LotQueueController::class, 'finishedGoods'])->name('finished-goods.index');

// ---- Audit trail ----
Route::get('/audit', [\App\Http\Controllers\AuditController::class, 'index'])->name('audit.index');

// ---- File attachments ----
Route::post('/attachments/{type}/{id}', [\App\Http\Controllers\AttachmentController::class, 'store'])->where('type', '[a-z_]+')->name('attachments.store');
Route::get('/attachments/{attachment}/download', [\App\Http\Controllers\AttachmentController::class, 'download'])->name('attachments.download');
Route::post('/attachments/{attachment}/cover', [\App\Http\Controllers\AttachmentController::class, 'cover'])->name('attachments.cover');
Route::post('/attachments/{attachment}/remove', [\App\Http\Controllers\AttachmentController::class, 'remove'])->name('attachments.remove');

// ---- Login, users, roles ----
Route::get('/login', [\App\Http\Controllers\AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('logout');
Route::get('/account/password', [\App\Http\Controllers\AuthController::class, 'showPassword'])->name('account.password');
Route::put('/account/password', [\App\Http\Controllers\AuthController::class, 'updatePassword'])->name('account.password.update');
Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
Route::get('/users/create', [\App\Http\Controllers\UserController::class, 'create'])->name('users.create');
Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
Route::get('/users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])->name('users.edit');
Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
Route::get('/roles', [\App\Http\Controllers\RoleController::class, 'index'])->name('roles.index');
Route::put('/roles', [\App\Http\Controllers\RoleController::class, 'update'])->name('roles.update');

// ---- Approvals ----
Route::get('/approvals', [\App\Http\Controllers\ApprovalController::class, 'index'])->name('approvals.index');
Route::post('/approvals/{approval}/decide', [\App\Http\Controllers\ApprovalController::class, 'decide'])->name('approvals.decide');
Route::post('/approvals/{approval}/cancel', [\App\Http\Controllers\ApprovalController::class, 'cancel'])->name('approvals.cancel');
Route::get('/approval-policies', [\App\Http\Controllers\ApprovalPolicyController::class, 'index'])->name('approval-policies.index');
Route::put('/approval-policies', [\App\Http\Controllers\ApprovalPolicyController::class, 'update'])->name('approval-policies.update');

// ---- Codes, barcodes, labels, scan ----
Route::get('/settings/codes', [\App\Http\Controllers\CodeSettingsController::class, 'edit'])->name('settings.codes');
Route::put('/settings/codes', [\App\Http\Controllers\CodeSettingsController::class, 'update'])->name('settings.codes.update');
Route::get('/scan', [\App\Http\Controllers\ScanController::class, 'find'])->name('scan');
Route::get('/labels/{type}/{id}', [\App\Http\Controllers\LabelController::class, 'show'])->where('type', '[a-z_]+')->name('labels.show');
Route::get('/tools/{type}/{id}', [\App\Http\Controllers\ToolController::class, 'show'])->where('type', '[a-z_]+')->name('tools.show');
Route::put('/tools/{type}/{id}/mark', [\App\Http\Controllers\ToolController::class, 'updateMark'])->where('type', '[a-z_]+')->name('tools.mark');
Route::put('/settings/interface', [\App\Http\Controllers\AppSettingController::class, 'updateInterface'])->name('settings.interface.update');
Route::get('/settings/display', [\App\Http\Controllers\DisplaySettingsController::class, 'edit'])->name('settings.display');
Route::put('/settings/display', [\App\Http\Controllers\DisplaySettingsController::class, 'update'])->name('settings.display.update');
Route::put('/settings/display/font', [\App\Http\Controllers\DisplaySettingsController::class, 'updateFont'])->name('settings.display.font');
Route::get('/branding/logo', [\App\Http\Controllers\BrandingController::class, 'logo'])->name('branding.logo');
Route::put('/settings/branding', [\App\Http\Controllers\BrandingController::class, 'update'])->name('settings.branding.update');

// People (HR)
Route::get('/directory', [\App\Http\Controllers\DirectoryController::class, 'index'])->name('directory.index');
Route::prefix('hr')->name('hr.')->group(function () {
    Route::get('/', [\App\Http\Controllers\HrDashboardController::class, 'index'])->name('dashboard');
    Route::get('/org-chart', [\App\Http\Controllers\OrgChartController::class, 'index'])->name('orgchart');
    Route::post('/org-chart/people', [\App\Http\Controllers\OrgChartController::class, 'store'])->name('orgchart.store');
    Route::prefix('employees')->name('employees.')->controller(\App\Http\Controllers\EmployeeController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/new', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{employee}', 'show')->name('show');
        Route::put('/{employee}', 'update')->name('update');
        Route::post('/{employee}/start-work', 'startWork')->name('start-work');
        Route::post('/{employee}/confirm', 'confirm')->name('confirm');
        Route::post('/{employee}/transfer', 'transfer')->name('transfer');
        Route::post('/{employee}/exit', 'startExit')->name('exit');
        Route::post('/{employee}/documents', [\App\Http\Controllers\EmployeeDocumentController::class, 'store'])->name('documents.store');
        Route::post('/{employee}/tasks', [\App\Http\Controllers\EmployeeTaskController::class, 'store'])->name('tasks.store');
    });
    Route::delete('/documents/{document}', [\App\Http\Controllers\EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('/tasks/{task}/toggle', [\App\Http\Controllers\EmployeeTaskController::class, 'toggle'])->name('tasks.toggle');
    Route::delete('/tasks/{task}', [\App\Http\Controllers\EmployeeTaskController::class, 'destroy'])->name('tasks.destroy');
    Route::prefix('exits')->name('exits.')->controller(\App\Http\Controllers\EmployeeExitController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{exit}', 'show')->name('show');
        Route::put('/{exit}', 'update')->name('update');
        Route::post('/{exit}/complete', 'complete')->name('complete');
        Route::post('/{exit}/cancel', 'cancel')->name('cancel');
    });
    Route::prefix('policies')->name('policies.')->controller(\App\Http\Controllers\HrPolicyController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/new', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{policy}', 'show')->name('show');
        Route::put('/{policy}', 'update')->name('update');
        Route::post('/{policy}/publish', 'publish')->name('publish');
        Route::post('/{policy}/revise', 'revise')->name('revise');
        Route::post('/{policy}/retire', 'retire')->name('retire');
        Route::post('/{policy}/acknowledge', 'acknowledge')->name('acknowledge');
    });
    Route::prefix('organisation')->controller(\App\Http\Controllers\HrOrganisationController::class)->group(function () {
        Route::get('/', 'index')->name('organisation');
        Route::post('/checklist', 'checklistStore')->name('checklist.store');
        Route::put('/checklist/{item}', 'checklistUpdate')->name('checklist.update');
        Route::delete('/checklist/{item}', 'checklistDestroy')->name('checklist.destroy');
        Route::post('/{kind}', 'store')->name('organisation.store');
        Route::put('/{kind}/{id}', 'update')->name('organisation.update');
        Route::delete('/{kind}/{id}', 'destroy')->name('organisation.destroy');
    });
});

// People: attendance, roster, leave
Route::prefix('hr')->name('hr.')->group(function () {
    Route::prefix('attendance')->controller(\App\Http\Controllers\AttendanceController::class)->group(function () {
        Route::get('/', 'daily')->name('attendance');
        Route::post('/', 'save')->name('attendance.save');
        Route::get('/person/{employee}', 'person')->name('attendance.person');
        Route::post('/close-day', 'closeDay')->name('attendance.close');
        Route::get('/exceptions', 'exceptions')->name('attendance.exceptions');
        Route::post('/exceptions/{record}', 'resolve')->name('attendance.resolve');
        Route::get('/overtime', 'overtime')->name('attendance.overtime');
        Route::post('/overtime', 'overtimeDecide')->name('attendance.overtime.decide');
        Route::get('/import', 'importForm')->name('attendance.import');
        Route::post('/import', 'import')->name('attendance.import.run');
    });
    Route::prefix('roster')->controller(\App\Http\Controllers\RosterController::class)->group(function () {
        Route::get('/', 'index')->name('roster');
        Route::post('/', 'save')->name('roster.save');
        Route::post('/shifts', 'shiftStore')->name('shifts.store');
        Route::put('/shifts/{shift}', 'shiftUpdate')->name('shifts.update');
        Route::post('/holidays', 'holidayStore')->name('holidays.store');
        Route::post('/holidays/fixed', 'holidayFixed')->name('holidays.fixed');
        Route::delete('/holidays/{holiday}', 'holidayDestroy')->name('holidays.destroy');
    });
    Route::prefix('leave')->controller(\App\Http\Controllers\HrLeaveController::class)->group(function () {
        Route::get('/', 'index')->name('leave');
        Route::post('/types', 'typeStore')->name('leave.types.store');
        Route::put('/types/{type}', 'typeUpdate')->name('leave.types.update');
        Route::post('/accrue', 'accrue')->name('leave.accrue');
        Route::post('/close-year', 'closeYear')->name('leave.close-year');
        Route::post('/employees/{employee}/adjust', 'adjust')->name('leave.adjust');
    });
});
Route::prefix('leave')->name('leave.')->controller(\App\Http\Controllers\LeaveController::class)->group(function () {
    Route::get('/', 'my')->name('my');
    Route::post('/', 'store')->name('store');
    Route::post('/{leave}/decide', 'decide')->name('decide');
    Route::post('/{leave}/cancel', 'cancel')->name('cancel');
});


// People: payroll (needs the hr.payroll permission), and a person's own payslips (login only)
Route::prefix('hr/payroll')->name('hr.payroll.')->group(function () {
    Route::controller(\App\Http\Controllers\PayrollController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/periods', 'store')->name('store');
        Route::get('/periods/{period}', 'show')->name('show');
        Route::post('/periods/{period}/calculate', 'calculate')->name('calculate');
        Route::post('/periods/{period}/approve', 'approve')->name('approve');
        Route::post('/periods/{period}/revert', 'revert')->name('revert');
        Route::post('/periods/{period}/pay', 'pay')->name('pay');
        Route::get('/periods/{period}/register.csv', 'register')->name('register');
        Route::get('/periods/{period}/bank.csv', 'bankFile')->name('bank');
        Route::get('/payslips/{payslip}', 'payslip')->name('payslip');
        Route::post('/payslips/{payslip}/hold', 'hold')->name('hold');
        Route::post('/payslips/{payslip}/release', 'release')->name('release');
        Route::post('/adjustments', 'adjustmentStore')->name('adjustments.store');
        Route::delete('/adjustments/{adjustment}', 'adjustmentDestroy')->name('adjustments.destroy');
    });
    Route::controller(\App\Http\Controllers\PayrollSetupController::class)->group(function () {
        Route::get('/setup', 'index')->name('setup');
        Route::post('/components', 'componentStore')->name('components.store');
        Route::put('/components/{component}', 'componentUpdate')->name('components.update');
        Route::put('/rules', 'rulesUpdate')->name('rules');
    });
    Route::controller(\App\Http\Controllers\SalaryController::class)->prefix('employees/{employee}')->group(function () {
        Route::post('/salary', 'revise')->name('salary');
        Route::put('/profile', 'profile')->name('profile');
        Route::post('/benefits', 'benefitStore')->name('benefits.store');
        Route::delete('/benefits/{benefit}', 'benefitDestroy')->name('benefits.destroy');
    });
    Route::controller(\App\Http\Controllers\AdvanceController::class)->prefix('advances')->group(function () {
        Route::get('/', 'index')->name('advances');
        Route::post('/', 'store')->name('advances.store');
        Route::get('/{advance}', 'show')->name('advances.show');
        Route::post('/{advance}/decide', 'decide')->name('advances.decide');
        Route::post('/{advance}/pause', 'pause')->name('advances.pause');
        Route::post('/{advance}/adjust', 'adjust')->name('advances.adjust');
    });
    Route::controller(\App\Http\Controllers\SettlementController::class)->group(function () {
        Route::get('/settlements', 'index')->name('settlements');
        Route::post('/exits/{exit}/settlement', 'open')->name('settlements.open');
        Route::get('/settlements/{settlement}', 'show')->name('settlements.show');
        Route::post('/settlements/{settlement}/calculate', 'calculate')->name('settlements.calculate');
        Route::post('/settlements/{settlement}/lines', 'addLine')->name('settlements.lines.add');
        Route::delete('/settlements/{settlement}/lines/{line}', 'removeLine')->name('settlements.lines.remove');
        Route::post('/settlements/{settlement}/approve', 'approve')->name('settlements.approve');
        Route::post('/settlements/{settlement}/pay', 'pay')->name('settlements.pay');
    });
    Route::get('/statutory', [\App\Http\Controllers\StatutoryController::class, 'index'])->name('statutory');
});
Route::prefix('payslips')->name('payslips.')->controller(\App\Http\Controllers\PayslipController::class)->group(function () {
    Route::get('/', 'my')->name('my');
    Route::get('/{payslip}', 'show')->name('show');
});
