<?php

use App\Http\Controllers\AdvanceController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalPolicyController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BaseSandController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CastingAnalysisController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CharacteristicLimitController;
use App\Http\Controllers\ChargeBatchController;
use App\Http\Controllers\CodeSettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\DefectController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\DocumentSettingsController;
use App\Http\Controllers\DisplaySettingsController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeExitController;
use App\Http\Controllers\EmployeeTaskController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\ErpDashboardController;
use App\Http\Controllers\FlaskController;
use App\Http\Controllers\FlaskSizeController;
use App\Http\Controllers\FurnaceController;
use App\Http\Controllers\HeatChemistryController;
use App\Http\Controllers\HeatMaterialConsumptionController;
use App\Http\Controllers\HeatPlanController;
use App\Http\Controllers\HeatReadingController;
use App\Http\Controllers\HeatTreatmentController;
use App\Http\Controllers\HrDashboardController;
use App\Http\Controllers\HrLeaveController;
use App\Http\Controllers\HrOrganisationController;
use App\Http\Controllers\HrPolicyController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\IronGradeController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\LotQueueController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialLotController;
use App\Http\Controllers\MaterialTestController;
use App\Http\Controllers\MouldingResourceController;
use App\Http\Controllers\MouldingRunBulkController;
use App\Http\Controllers\MouldingRunController;
use App\Http\Controllers\MouldReadinessController;
use App\Http\Controllers\NcrController;
use App\Http\Controllers\OrderTraceController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\PackingController;
use App\Http\Controllers\PatternCavityController;
use App\Http\Controllers\PatternController;
use App\Http\Controllers\PatternOwnershipController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollSetupController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PourController;
use App\Http\Controllers\ProcessControlController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\QuotationSettingsController;
use App\Http\Controllers\RequestWizardController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SandSystemController;
use App\Http\Controllers\SandTestController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\StatutoryController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockReconciliationController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\ThermalTestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkListController;
use App\Http\Controllers\YieldReportController;
use Illuminate\Support\Facades\Route;

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
        Route::get('/bulk', [MouldingRunBulkController::class, 'edit'])->name('bulk');
        Route::post('/bulk', [MouldingRunBulkController::class, 'save'])->name('bulk.save');
        Route::get('/{mouldingRun}', 'show')->name('show');
        Route::put('/{mouldingRun}/crew', 'updateCrew')->name('crew');
    });

Route::prefix('patterns')->name('patterns.')->controller(PatternController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{pattern}/edit', 'edit')->name('edit');
    Route::put('/{pattern}', 'update')->name('update');
    Route::get('/{pattern}', 'show')->name('show')->withTrashed();
    Route::delete('/{pattern}', 'archive')->name('archive');
    Route::post('/{pattern}/restore', 'restore')->name('restore')->withTrashed();
    Route::put('/{pattern}/sand', 'updateSand')->name('sand');
    Route::post('/{pattern}/revisions', 'storeRevision')->name('revisions.store');
    Route::put('/{pattern}/revisions/{revision}/layout', 'updateLayout')->name('revisions.layout');
    Route::put('/{pattern}/revisions/{revision}/status', 'updateRevisionStatus')->name('revisions.status');
    Route::get('/{pattern}/revisions/{revision}/edit', 'editRevision')->name('revisions.edit');
    Route::put('/{pattern}/revisions/{revision}', 'updateRevisionDetails')->name('revisions.update');
    Route::post('/{pattern}/cavities', [PatternCavityController::class, 'add'])->name('cavities.add');
    Route::put('/{pattern}/revisions/{revision}/cavity-map', [PatternCavityController::class, 'saveMap'])->name('cavities.map');
    Route::post('/{pattern}/revisions/{revision}/cavities', [PatternCavityController::class, 'store'])->name('cavities.store');
    Route::put('/{pattern}/revisions/{revision}/cavities/{cavity}', [PatternCavityController::class, 'update'])->name('cavities.update');
    Route::put('/{pattern}/revisions/{revision}/cavities/{cavity}/reviewed', [PatternCavityController::class, 'review'])->name('cavities.review');
    Route::delete('/{pattern}/revisions/{revision}/cavities/{cavity}', [PatternCavityController::class, 'destroy'])->name('cavities.destroy');
});

Route::prefix('casting-analyses')
    ->name('casting-analyses.')
    ->controller(CastingAnalysisController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{analysis}', 'show')->name('show');
        Route::get('/{analysis}/field', 'field')->name('field');
        Route::post('/{analysis}/rerun', 'rerun')->name('rerun');
        Route::delete('/{analysis}', 'destroy')->name('destroy');
    });

Route::prefix('imports')->name('imports.')->controller(DataImportController::class)->group(function () {
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
        Route::get('/{product}', 'show')->name('show')->withTrashed();
        Route::delete('/{product}', 'archive')->name('archive');
        Route::post('/{product}/restore', 'restore')->name('restore')->withTrashed();
    });

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
        Route::get('/{furnace}', 'show')->name('show');
    });

Route::post(
    '/heats/{heat}/charge-entries',
    [HeatMaterialConsumptionController::class, 'store']
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
        Route::post('/{material}/lots/{lot}/post-receipt', [MaterialLotController::class, 'postReceipt'])->name('lots.post-receipt');
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
    [HeatReadingController::class, 'postCharge']
)->name('heats.charge-postings.store');

Route::prefix('settings')
    ->name('settings.')
    ->controller(AppSettingController::class)
    ->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
    });

// ---- Orders, contract review, planning, thermal analysis, quality gates ----
Route::prefix('customers')->name('customers.')->controller(CustomerController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
});

Route::prefix('sales-orders')->name('sales-orders.')->controller(SalesOrderController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{salesOrder}', 'show')->name('show');
    Route::post('/{salesOrder}/review', 'review')->name('review');
    Route::post('/{salesOrder}/review/acknowledge', 'acknowledge')->name('review.acknowledge');
    Route::post('/{salesOrder}/review/sign', 'sign')->name('review.sign');
    Route::post('/{salesOrder}/lines/{line}/production-order', 'productionOrder')->name('lines.production-order');
    Route::get('/{salesOrder}/trace', [OrderTraceController::class, 'show'])->name('trace');
});

Route::prefix('iron-grades')->name('iron-grades.')->controller(IronGradeController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{ironGrade}', 'show')->name('show');
    Route::put('/{ironGrade}', 'update')->name('update');
    Route::put('/{ironGrade}/thermal-window', 'updateWindow')->name('window');
    Route::put('/{ironGrade}/thermal-window/white', 'updateWhiteWindow')->name('window.white');
    Route::post('/{ironGrade}/furnace-capability', 'setCapability')->name('capability');
});

Route::prefix('thermal')->name('thermal.')->controller(ThermalTestController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/sample-curve.csv', 'sample')->name('sample');
    Route::post('/calibrate', 'calibrate')->name('calibrate');
    Route::get('/{test}', 'show')->name('show');
});

Route::prefix('heat-plan')->name('heat-plan.')->controller(HeatPlanController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/rebuild', 'rebuild')->name('rebuild');
    Route::post('/{plannedHeat}/furnace', 'assign')->name('assign');
    Route::post('/{plannedHeat}/release', 'release')->name('release');
});

Route::post('/pours/{pour}/start', [PourController::class, 'start'])->name('pours.start');
Route::post('/pours/{pour}/complete', [PourController::class, 'complete'])->name('pours.complete');

// ---- Shop floor: chemistry, pour assemblies, casting lots, inspection, packing, dispatch ----
Route::post('/heats/{heat}/chemistry', [HeatChemistryController::class, 'store'])->name('heats.chemistry.store');
Route::post('/heats/{heat}/chemistry/import', [\App\Http\Controllers\HeatChemistryImportController::class, 'store'])->name('heats.chemistry.import');
Route::get('/heats/{heat}/summary', [\App\Http\Controllers\HeatSummaryController::class, 'show'])->name('heats.summary');
Route::put('/heats/{heat}/timeline', [\App\Http\Controllers\HeatTimelineController::class, 'update'])->name('heats.timeline');
Route::post('/heats/{heat}/chemistry/{reading}/verify', [HeatChemistryController::class, 'verify'])->name('heats.chemistry.verify');
Route::post('/moulding-runs/{mouldingRun}/assemblies', [MouldingRunController::class, 'assemble'])->name('moulding-runs.assemble');
Route::post('/pours/{pour}/assemblies', [PourController::class, 'attachAssemblies'])->name('pours.assemblies.attach');
Route::delete('/pours/{pour}/assemblies/{row}', [PourController::class, 'detachAssembly'])->name('pours.assemblies.detach');
Route::put('/pours/{pour}/assemblies/{row}/cover', [PourController::class, 'coverAssembly'])->name('pours.assemblies.cover');

// ---- Pattern ownership ----
Route::controller(PatternOwnershipController::class)->group(function () {
    Route::get('/pattern-ownership', 'index')->name('pattern-ownership.index');
    Route::get('/settings/pattern-ownership', 'editRules')->name('settings.pattern-ownership.edit');
    Route::put('/settings/pattern-ownership', 'rules')->name('settings.pattern-ownership');
    Route::put('/patterns/{pattern}/ownership', 'update')->name('patterns.ownership');
    Route::post('/patterns/{pattern}/pay-off', 'payOff')->name('patterns.pay-off');
    Route::post('/patterns/{pattern}/return', 'returnToCustomer')->name('patterns.return');
    Route::post('/patterns/{pattern}/scrap', 'scrap')->name('patterns.scrap');
});

// ---- Flasks ----
Route::prefix('flasks')->name('flasks.')->controller(FlaskController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('/{flask}', 'show')->name('show');
    Route::get('/{flask}/edit', 'edit')->name('edit');
    Route::put('/{flask}', 'update')->name('update');
    Route::post('/{flask}/status', 'status')->name('status');
    Route::post('/{flask}/release', 'release')->name('release');
});
Route::get('/settings/flasks', [FlaskController::class, 'editRules'])->name('settings.flasks.edit');
Route::put('/settings/flasks', [FlaskController::class, 'rules'])->name('settings.flasks');
Route::prefix('corrective-actions')->name('capa.')->controller(\App\Http\Controllers\CapaController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/from-ncr/{ncr}', 'fromNcr')->name('from-ncr');
    Route::get('/{capa}', 'show')->name('show');
    Route::put('/{capa}', 'update')->name('update');
    Route::post('/{capa}/complete', 'complete')->name('complete');
    Route::post('/{capa}/verify', 'verify')->name('verify');
    Route::post('/{capa}/cancel', 'cancel')->name('cancel');
});
Route::get('/settings/modules', [\App\Http\Controllers\ModuleSettingsController::class, 'edit'])->name('settings.modules.edit');
Route::put('/settings/modules', [\App\Http\Controllers\ModuleSettingsController::class, 'update'])->name('settings.modules');
Route::get('/settings/pouring', [\App\Http\Controllers\PouringRulesController::class, 'edit'])->name('settings.pouring.edit');
Route::put('/settings/pouring', [\App\Http\Controllers\PouringRulesController::class, 'update'])->name('settings.pouring');
Route::prefix('flask-sizes')->name('flask-sizes.')->controller(FlaskSizeController::class)->group(function () {
    Route::post('/', 'store')->name('store');
    Route::get('/{flaskSize}/edit', 'edit')->name('edit');
    Route::put('/{flaskSize}', 'update')->name('update');
});
Route::put('/moulding-runs/{mouldingRun}/layers/{layer}/flask', [MouldingRunController::class, 'swapFlask'])->name('moulding-runs.layers.flask');
Route::post('/moulding-runs/{mouldingRun}/assemblies/{assembly}/release-flasks', [MouldingRunController::class, 'releaseMouldFlasks'])->name('moulding-runs.assemblies.release');
Route::post('/moulding-runs/{mouldingRun}/release-flasks', [MouldingRunController::class, 'releaseRunFlasks'])->name('moulding-runs.release-flasks');

// ---- Casting lots, inspections, NCRs, packing, dispatch, certificate ----
Route::prefix('lots')->name('lots.')->controller(LotController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/queue', [LotQueueController::class, 'queue'])->name('queue');
    Route::get('/{lot}', 'show')->name('show');
    Route::post('/{lot}/stages', 'storeStage')->name('stages.store');
    Route::post('/{lot}/hold', 'hold')->name('hold');
    Route::post('/{lot}/release', 'release')->name('release');
    Route::get('/{lot}/certificate', [CertificateController::class, 'show'])->name('certificate');
    Route::get('/{lot}/inspections/create', [InspectionController::class, 'create'])->name('inspections.create');
    Route::post('/{lot}/inspections', [InspectionController::class, 'store'])->name('inspections.store');
});
Route::prefix('inspections')->name('inspections.')->controller(InspectionController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{inspection}', 'show')->name('show');
    Route::post('/{inspection}/results', 'saveResults')->name('results.save');
    Route::post('/{inspection}/results/add', 'addResult')->name('results.add');
    Route::post('/{inspection}/complete', 'complete')->name('complete');
});
Route::prefix('ncrs')->name('ncrs.')->controller(NcrController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{ncr}', 'show')->name('show');
    Route::put('/{ncr}', 'update')->name('update');
    Route::post('/{ncr}/close', 'close')->name('close');
});
Route::prefix('packing')->name('packing.')->controller(PackingController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{packing}', 'show')->name('show');
});
Route::prefix('dispatches')->name('dispatches.')->controller(DispatchController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{dispatch}', 'show')->name('show');
});

// ---- Moulding teams, stations, daily work lists, mould readiness ----
Route::post('/moulding-runs/{mouldingRun}/output', [MouldingRunController::class, 'recordOutput'])->name('moulding-runs.output');
Route::controller(MouldingResourceController::class)->prefix('moulding-teams')->name('moulding-teams.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/teams', 'storeTeam')->name('teams.store');
    Route::put('/teams/{team}', 'updateTeam')->name('teams.update');
    Route::post('/stations', 'storeStation')->name('stations.store');
    Route::put('/stations/{station}', 'updateStation')->name('stations.update');
});
Route::prefix('sand-systems')->name('sand-systems.')->controller(SandSystemController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{sandSystem}', 'update')->name('update');
    Route::delete('/{sandSystem}', 'destroy')->name('destroy');
    Route::post('/sands', [BaseSandController::class, 'store'])->name('base.store');
    Route::put('/sands/{baseSand}', [BaseSandController::class, 'update'])->name('base.update');
    Route::delete('/sands/{baseSand}', [BaseSandController::class, 'destroy'])->name('base.destroy');
});
Route::prefix('work-lists')->name('work-lists.')->controller(WorkListController::class)->group(function () {
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
Route::get('/mould-readiness', [MouldReadinessController::class, 'index'])->name('mould-readiness.index');

// ---- Custom fields ----
Route::get('/settings/custom-fields', [CustomFieldController::class, 'index'])->name('custom-fields.index');
Route::post('/settings/custom-fields', [CustomFieldController::class, 'store'])->name('custom-fields.store');
Route::put('/settings/custom-fields/{field}', [CustomFieldController::class, 'update'])->name('custom-fields.update');
Route::post('/custom-fields/{entity}/{id}', [CustomFieldController::class, 'saveValues'])->name('custom-fields.values');

// ---- Measurements: material tests and limits ----
Route::post('/heats/{heat}/tests/{kind}', [MaterialTestController::class, 'store'])->name('heats.tests.store');
Route::post('/limits', [CharacteristicLimitController::class, 'store'])->name('limits.store');
Route::put('/limits/{limit}', [CharacteristicLimitController::class, 'update'])->name('limits.update');
Route::delete('/limits/{limit}', [CharacteristicLimitController::class, 'destroy'])->name('limits.destroy');

// ---- Sand lab ----
Route::get('/sand-tests', [SandTestController::class, 'index'])->name('sand-tests.index');
Route::post('/sand-tests', [SandTestController::class, 'store'])->name('sand-tests.store');

// ---- Melt treatment and process controls ----
Route::post('/heats/{heat}/treatments', [HeatTreatmentController::class, 'store'])->name('heats.treatments.store');
Route::delete('/heats/{heat}/treatments/{treatment}', [HeatTreatmentController::class, 'destroy'])->name('heats.treatments.destroy');
Route::put('/settings/process-controls', [ProcessControlController::class, 'update'])->name('process-controls.update');
Route::put('/pattern-revisions/{revision}/cooling', [ProcessControlController::class, 'cooling'])->name('pattern-revisions.cooling');

// ---- Reports and defect list ----
Route::get('/reports/yield', [YieldReportController::class, 'index'])->name('reports.yield');
Route::get('/defects', [DefectController::class, 'index'])->name('defects.index');
Route::post('/defects', [DefectController::class, 'store'])->name('defects.store');
Route::put('/defects/{defect}', [DefectController::class, 'update'])->name('defects.update');
Route::get('/finished-goods', [LotQueueController::class, 'finishedGoods'])->name('finished-goods.index');

// ---- Audit trail ----
Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

// ---- File attachments ----
Route::post('/attachments/{type}/{id}', [AttachmentController::class, 'store'])->where('type', '[a-z_]+')->name('attachments.store');
Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
Route::get('/attachments/find', [AttachmentController::class, 'find'])->name('attachments.find');
Route::post('/attachments/{attachment}/link/{type}/{id}', [AttachmentController::class, 'link'])->where('type', '[a-z_]+')->name('attachments.link');
Route::post('/attachment-links/{link}/remove', [AttachmentController::class, 'unlink'])->name('attachments.unlink');
Route::post('/attachments/{type}/{id}/pin', [AttachmentController::class, 'pin'])->where('type', '[a-z_]+')->name('attachments.pin');
Route::post('/attachments/{attachment}/version', [AttachmentController::class, 'version'])->name('attachments.version');
Route::post('/attachments/{attachment}/cover', [AttachmentController::class, 'cover'])->name('attachments.cover');
Route::post('/attachments/{attachment}/remove', [AttachmentController::class, 'remove'])->name('attachments.remove');

// ---- Login, users, roles ----
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/account/password', [AuthController::class, 'showPassword'])->name('account.password');
Route::put('/account/password', [AuthController::class, 'updatePassword'])->name('account.password.update');
Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
Route::put('/roles', [RoleController::class, 'update'])->name('roles.update');

// ---- Approvals ----
Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
Route::post('/approvals/{approval}/decide', [ApprovalController::class, 'decide'])->name('approvals.decide');
Route::post('/approvals/{approval}/cancel', [ApprovalController::class, 'cancel'])->name('approvals.cancel');
Route::get('/approval-policies', [ApprovalPolicyController::class, 'index'])->name('approval-policies.index');
Route::put('/approval-policies', [ApprovalPolicyController::class, 'update'])->name('approval-policies.update');

// ---- Codes, barcodes, labels, scan ----
Route::get('/settings/codes', [CodeSettingsController::class, 'edit'])->name('settings.codes');
Route::put('/settings/codes', [CodeSettingsController::class, 'update'])->name('settings.codes.update');
Route::get('/settings/documents', [DocumentSettingsController::class, 'edit'])->name('settings.documents');
Route::put('/settings/documents', [DocumentSettingsController::class, 'update'])->name('settings.documents.update');
Route::get('/scan', [ScanController::class, 'find'])->name('scan');
Route::get('/scan/suggest', [ScanController::class, 'suggest'])->name('scan.suggest');
Route::get('/labels/{type}/{id}', [LabelController::class, 'show'])->where('type', '[a-z_]+')->name('labels.show');
Route::put('/settings/interface', [AppSettingController::class, 'updateInterface'])->name('settings.interface.update');
Route::get('/settings/display', [DisplaySettingsController::class, 'edit'])->name('settings.display');
Route::put('/settings/display', [DisplaySettingsController::class, 'update'])->name('settings.display.update');
Route::put('/settings/display/font', [DisplaySettingsController::class, 'updateFont'])->name('settings.display.font');
Route::put('/settings/display/formats', [DisplaySettingsController::class, 'updateFormats'])->name('settings.display.formats');
Route::get('/branding/logo', [BrandingController::class, 'logo'])->name('branding.logo');
Route::put('/settings/branding', [BrandingController::class, 'update'])->name('settings.branding.update');

// People (HR)
Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
Route::prefix('hr')->name('hr.')->group(function () {
    Route::get('/', [HrDashboardController::class, 'index'])->name('dashboard');
    Route::get('/org-chart', [OrgChartController::class, 'index'])->name('orgchart');
    Route::post('/org-chart/people', [OrgChartController::class, 'store'])->name('orgchart.store');
    Route::prefix('employees')->name('employees.')->controller(EmployeeController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/new', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{employee}', 'show')->name('show');
        Route::put('/{employee}', 'update')->name('update');
        Route::post('/{employee}/start-work', 'startWork')->name('start-work');
        Route::post('/{employee}/confirm', 'confirm')->name('confirm');
        Route::post('/{employee}/transfer', 'transfer')->name('transfer');
        Route::post('/{employee}/exit', 'startExit')->name('exit');
        Route::post('/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('documents.store');
        Route::post('/{employee}/tasks', [EmployeeTaskController::class, 'store'])->name('tasks.store');
    });
    Route::delete('/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('/tasks/{task}/toggle', [EmployeeTaskController::class, 'toggle'])->name('tasks.toggle');
    Route::delete('/tasks/{task}', [EmployeeTaskController::class, 'destroy'])->name('tasks.destroy');
    Route::prefix('exits')->name('exits.')->controller(EmployeeExitController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{exit}', 'show')->name('show');
        Route::put('/{exit}', 'update')->name('update');
        Route::post('/{exit}/complete', 'complete')->name('complete');
        Route::post('/{exit}/cancel', 'cancel')->name('cancel');
    });
    Route::prefix('policies')->name('policies.')->controller(HrPolicyController::class)->group(function () {
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
    Route::prefix('organisation')->controller(HrOrganisationController::class)->group(function () {
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
    Route::prefix('attendance')->controller(AttendanceController::class)->group(function () {
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
    Route::prefix('roster')->controller(RosterController::class)->group(function () {
        Route::get('/', 'index')->name('roster');
        Route::post('/', 'save')->name('roster.save');
        Route::post('/shifts', 'shiftStore')->name('shifts.store');
        Route::put('/shifts/{shift}', 'shiftUpdate')->name('shifts.update');
        Route::post('/holidays', 'holidayStore')->name('holidays.store');
        Route::post('/holidays/fixed', 'holidayFixed')->name('holidays.fixed');
        Route::delete('/holidays/{holiday}', 'holidayDestroy')->name('holidays.destroy');
    });
    Route::prefix('leave')->controller(HrLeaveController::class)->group(function () {
        Route::get('/', 'index')->name('leave');
        Route::post('/types', 'typeStore')->name('leave.types.store');
        Route::put('/types/{type}', 'typeUpdate')->name('leave.types.update');
        Route::post('/accrue', 'accrue')->name('leave.accrue');
        Route::post('/close-year', 'closeYear')->name('leave.close-year');
        Route::post('/employees/{employee}/adjust', 'adjust')->name('leave.adjust');
    });
});
Route::prefix('leave')->name('leave.')->controller(LeaveController::class)->group(function () {
    Route::get('/', 'my')->name('my');
    Route::post('/', 'store')->name('store');
    Route::post('/{leave}/decide', 'decide')->name('decide');
    Route::post('/{leave}/cancel', 'cancel')->name('cancel');
});

// People: payroll (needs the hr.payroll permission), and a person's own payslips (login only)
Route::prefix('hr/payroll')->name('hr.payroll.')->group(function () {
    Route::controller(PayrollController::class)->group(function () {
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
    Route::controller(PayrollSetupController::class)->group(function () {
        Route::get('/setup', 'index')->name('setup');
        Route::post('/components', 'componentStore')->name('components.store');
        Route::put('/components/{component}', 'componentUpdate')->name('components.update');
        Route::put('/rules', 'rulesUpdate')->name('rules');
    });
    Route::controller(SalaryController::class)->prefix('employees/{employee}')->group(function () {
        Route::post('/salary', 'revise')->name('salary');
        Route::put('/profile', 'profile')->name('profile');
        Route::post('/benefits', 'benefitStore')->name('benefits.store');
        Route::delete('/benefits/{benefit}', 'benefitDestroy')->name('benefits.destroy');
    });
    Route::controller(AdvanceController::class)->prefix('advances')->group(function () {
        Route::get('/', 'index')->name('advances');
        Route::post('/', 'store')->name('advances.store');
        Route::get('/{advance}', 'show')->name('advances.show');
        Route::post('/{advance}/decide', 'decide')->name('advances.decide');
        Route::post('/{advance}/pause', 'pause')->name('advances.pause');
        Route::post('/{advance}/adjust', 'adjust')->name('advances.adjust');
    });
    Route::controller(SettlementController::class)->group(function () {
        Route::get('/settlements', 'index')->name('settlements');
        Route::post('/exits/{exit}/settlement', 'open')->name('settlements.open');
        Route::get('/settlements/{settlement}', 'show')->name('settlements.show');
        Route::post('/settlements/{settlement}/calculate', 'calculate')->name('settlements.calculate');
        Route::post('/settlements/{settlement}/lines', 'addLine')->name('settlements.lines.add');
        Route::delete('/settlements/{settlement}/lines/{line}', 'removeLine')->name('settlements.lines.remove');
        Route::post('/settlements/{settlement}/approve', 'approve')->name('settlements.approve');
        Route::post('/settlements/{settlement}/pay', 'pay')->name('settlements.pay');
    });
    Route::get('/statutory', [StatutoryController::class, 'index'])->name('statutory');
});
Route::prefix('payslips')->name('payslips.')->controller(PayslipController::class)->group(function () {
    Route::get('/', 'my')->name('my');
    Route::get('/{payslip}', 'show')->name('show');
});
