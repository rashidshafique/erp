<?php

use App\Domains\Hr\Http\Controllers\AdvanceController;
use App\Domains\Hr\Http\Controllers\AttendanceController;
use App\Domains\Hr\Http\Controllers\DirectoryController;
use App\Domains\Hr\Http\Controllers\EmployeeController;
use App\Domains\Hr\Http\Controllers\EmployeeDocumentController;
use App\Domains\Hr\Http\Controllers\EmployeeExitController;
use App\Domains\Hr\Http\Controllers\EmployeeTaskController;
use App\Domains\Hr\Http\Controllers\HrDashboardController;
use App\Domains\Hr\Http\Controllers\HrLeaveController;
use App\Domains\Hr\Http\Controllers\HrOrganisationController;
use App\Domains\Hr\Http\Controllers\HrPolicyController;
use App\Domains\Hr\Http\Controllers\LeaveController;
use App\Domains\Hr\Http\Controllers\OrgChartController;
use App\Domains\Hr\Http\Controllers\PayrollController;
use App\Domains\Hr\Http\Controllers\PayrollSetupController;
use App\Domains\Hr\Http\Controllers\PayslipController;
use App\Domains\Hr\Http\Controllers\RosterController;
use App\Domains\Hr\Http\Controllers\SalaryController;
use App\Domains\Hr\Http\Controllers\SettlementController;
use App\Domains\Hr\Http\Controllers\StatutoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
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
            Route::put('/defaults', 'defaultsUpdate')->name('defaults.update');
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
});
