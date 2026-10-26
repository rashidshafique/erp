<?php

namespace App\Domains\Hr;

use App\Domains\Core\Contracts\PeopleDirectory;
use App\Domains\Core\ModuleRegistry;
use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Hr\Console\HrCloseAttendance;
use App\Domains\Hr\Console\HrLeaveAccrue;
use App\Domains\Hr\Models\AdvanceLedgerEntry;
use App\Domains\Hr\Models\AttendanceRecord;
use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\EmployeeAdvance;
use App\Domains\Hr\Models\EmployeeBenefit;
use App\Domains\Hr\Models\EmployeeDocument;
use App\Domains\Hr\Models\EmployeeEvent;
use App\Domains\Hr\Models\EmployeeExit;
use App\Domains\Hr\Models\EmployeeSalary;
use App\Domains\Hr\Models\EmployeeSalaryLine;
use App\Domains\Hr\Models\EmployeeSettlement;
use App\Domains\Hr\Models\EmployeeTask;
use App\Domains\Hr\Models\HrChecklistItem;
use App\Domains\Hr\Models\HrCostCentre;
use App\Domains\Hr\Models\HrDepartment;
use App\Domains\Hr\Models\HrDesignation;
use App\Domains\Hr\Models\HrGrade;
use App\Domains\Hr\Models\HrHoliday;
use App\Domains\Hr\Models\HrLocation;
use App\Domains\Hr\Models\HrPolicy;
use App\Domains\Hr\Models\HrPolicyAck;
use App\Domains\Hr\Models\HrRosterEntry;
use App\Domains\Hr\Models\HrShift;
use App\Domains\Hr\Models\LeaveLedgerEntry;
use App\Domains\Hr\Models\LeaveRequest;
use App\Domains\Hr\Models\LeaveType;
use App\Domains\Hr\Models\PayrollAdjustment;
use App\Domains\Hr\Models\PayrollPeriod;
use App\Domains\Hr\Models\Payslip;
use App\Domains\Hr\Models\PayslipLine;
use App\Domains\Hr\Models\SettlementLine;
use App\Domains\Hr\Services\AdvanceService;
use App\Domains\Hr\Services\AttendanceCalculator;
use App\Domains\Hr\Services\AttendanceService;
use App\Domains\Hr\Services\DayPlan;
use App\Domains\Hr\Services\LeaveAccrual;
use App\Domains\Hr\Services\LeaveService;
use App\Domains\Hr\Services\Lifecycle;
use App\Domains\Hr\Services\PayrollCalculator;
use App\Domains\Hr\Services\PayrollRules;
use App\Domains\Hr\Services\PayrollService;
use App\Domains\Hr\Services\SalaryService;
use App\Domains\Hr\Services\SettlementService;
use App\Domains\Hr\Support\EmployeePeopleDirectory;
use App\Domains\Hr\Support\HrAccess;
use Illuminate\Console\Scheduling\Schedule;

/** People: employees, organisation, attendance, leave and policies, and the payroll that follows from them. */
class HrServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'hr';
    }

    protected function moduleLabel(): string
    {
        return 'People (HR) & payroll';
    }

    protected function moduleDescription(): string
    {
        return 'Employees, organisation, attendance, leave, policies and payroll. Moulding teams can no longer be built from the employee list.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function register(): void
    {
        parent::register();
        $this->mergeConfigFrom(__DIR__.'/config/payroll.php', 'payroll');
    }

    public function boot(): void
    {
        parent::boot();
        if ($this->app->make(ModuleRegistry::class)->isEnabled($this->moduleName())) {
            $this->app->singleton(PeopleDirectory::class, EmployeePeopleDirectory::class);
        }
    }

    protected function classAliases(): array
    {
        return [
            'App\Models\AdvanceLedgerEntry' => AdvanceLedgerEntry::class,
            'App\Models\AttendanceRecord' => AttendanceRecord::class,
            'App\Models\Employee' => Employee::class,
            'App\Models\EmployeeAdvance' => EmployeeAdvance::class,
            'App\Models\EmployeeBenefit' => EmployeeBenefit::class,
            'App\Models\EmployeeDocument' => EmployeeDocument::class,
            'App\Models\EmployeeEvent' => EmployeeEvent::class,
            'App\Models\EmployeeExit' => EmployeeExit::class,
            'App\Models\EmployeeSalary' => EmployeeSalary::class,
            'App\Models\EmployeeSalaryLine' => EmployeeSalaryLine::class,
            'App\Models\EmployeeSettlement' => EmployeeSettlement::class,
            'App\Models\EmployeeTask' => EmployeeTask::class,
            'App\Models\HrChecklistItem' => HrChecklistItem::class,
            'App\Models\HrCostCentre' => HrCostCentre::class,
            'App\Models\HrDepartment' => HrDepartment::class,
            'App\Models\HrDesignation' => HrDesignation::class,
            'App\Models\HrGrade' => HrGrade::class,
            'App\Models\HrHoliday' => HrHoliday::class,
            'App\Models\HrLocation' => HrLocation::class,
            'App\Models\HrPolicy' => HrPolicy::class,
            'App\Models\HrPolicyAck' => HrPolicyAck::class,
            'App\Models\HrRosterEntry' => HrRosterEntry::class,
            'App\Models\HrShift' => HrShift::class,
            'App\Models\LeaveLedgerEntry' => LeaveLedgerEntry::class,
            'App\Models\LeaveRequest' => LeaveRequest::class,
            'App\Models\LeaveType' => LeaveType::class,
            'App\Models\PayrollAdjustment' => PayrollAdjustment::class,
            'App\Models\PayrollPeriod' => PayrollPeriod::class,
            'App\Models\Payslip' => Payslip::class,
            'App\Models\PayslipLine' => PayslipLine::class,
            'App\Models\SettlementLine' => SettlementLine::class,
            'App\Services\Payroll\AdvanceService' => AdvanceService::class,
            'App\Services\Hr\AttendanceCalculator' => AttendanceCalculator::class,
            'App\Services\Hr\AttendanceService' => AttendanceService::class,
            'App\Services\Hr\DayPlan' => DayPlan::class,
            'App\Services\Hr\LeaveAccrual' => LeaveAccrual::class,
            'App\Services\Hr\LeaveService' => LeaveService::class,
            'App\Services\Hr\Lifecycle' => Lifecycle::class,
            'App\Services\Payroll\PayrollCalculator' => PayrollCalculator::class,
            'App\Services\Payroll\PayrollRules' => PayrollRules::class,
            'App\Services\Payroll\PayrollService' => PayrollService::class,
            'App\Services\Payroll\SalaryService' => SalaryService::class,
            'App\Services\Payroll\SettlementService' => SettlementService::class,
            'App\Support\HrAccess' => HrAccess::class,
        ];
    }

    protected function navGroups(): array
    {
        return [[
            'key' => 'hr', 'label' => 'People (HR)', 'icon' => 'users', 'active' => ['hr.*', 'directory.*', 'leave.*'],
            'items' => [
                ['label' => 'People overview', 'route' => 'hr.dashboard', 'icon' => 'dashboard', 'active' => ['hr.dashboard']],
                ['label' => 'Employees', 'route' => 'hr.employees.index', 'icon' => 'id-card', 'active' => ['hr.employees.*']],
                ['label' => 'Attendance & overtime', 'route' => 'hr.attendance', 'icon' => 'clock', 'active' => ['hr.attendance*']],
                ['label' => 'Roster, shifts & holidays', 'route' => 'hr.roster', 'icon' => 'calendar', 'active' => ['hr.roster*', 'hr.shifts.*', 'hr.holidays.*']],
                ['label' => 'Leave (HR)', 'route' => 'hr.leave', 'icon' => 'calendar-minus', 'active' => ['hr.leave*']],
                ['label' => 'Payroll', 'route' => 'hr.payroll.index', 'icon' => 'wallet', 'active' => ['hr.payroll.*'], 'visible' => fn () => HrAccess::isPayroll()],
                ['label' => 'My leave & approvals', 'route' => 'leave.my', 'icon' => 'calendar-minus', 'active' => ['leave.*']],
                ['label' => 'My payslips', 'route' => 'payslips.my', 'icon' => 'receipt', 'active' => ['payslips.*']],
                ['label' => 'Org chart', 'route' => 'hr.orgchart', 'icon' => 'network', 'active' => ['hr.orgchart*']],
                ['label' => 'Directory & reporting lines', 'route' => 'directory.index', 'icon' => 'list', 'active' => ['directory.*']],
                ['label' => 'Exits & clearance', 'route' => 'hr.exits.index', 'icon' => 'log-out', 'active' => ['hr.exits.*']],
                ['label' => 'HR policies', 'route' => 'hr.policies.index', 'icon' => 'book', 'active' => ['hr.policies.*']],
                ['label' => 'Organisation setup', 'route' => 'hr.organisation', 'icon' => 'sliders', 'active' => ['hr.organisation*', 'hr.checklist.*']],
            ],
        ]];
    }

    protected function permissionCatalog(): array
    {
        return [
            'hr.manage' => 'People: employees, organisation, policies, leave, attendance',
            'hr.payroll' => 'People: salaries, payroll, loans and settlements',
        ];
    }

    protected function openRoutes(): array
    {
        return ['leave.*', 'payslips.*'];
    }

    protected function roleDefaults(): array
    {
        return [
            'HR_MANAGER' => ['name' => 'HR manager', 'permissions' => ['hr.manage', 'hr.payroll', 'attachments.manage', 'documents.sensitive', 'approvals.decide', 'approvals.configure', 'audit.view']],
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'hr.payroll.*', 'permission' => 'hr.payroll', 'reads' => true],
            ['pattern' => 'hr.*', 'permission' => 'hr.manage', 'reads' => true],
        ];
    }

    protected function approvalActions(): array
    {
        return [
            'hr.confirm_employee' => 'Confirm an employee after probation',
            'hr.transfer_employee' => 'Transfer or promote an employee',
            'hr.finalise_exit' => 'Complete an employee exit',
            'hr.approve_payroll' => 'Approve a payroll run',
            'hr.approve_advance' => 'Approve an advance or loan',
            'hr.approve_settlement' => 'Approve a final settlement',
        ];
    }

    protected function consoleCommands(): array
    {
        return [HrCloseAttendance::class, HrLeaveAccrue::class];
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('hr:close-attendance')->dailyAt('01:30');
        $schedule->command('hr:leave-accrue')->dailyAt('02:00');
    }

    protected function attachables(): array
    {
        return [
            'employee' => ['label' => 'Employee', 'model' => Employee::class, 'edit' => 'hr.manage', 'view' => 'hr.manage'],
            'employee_document' => ['label' => 'Employee document', 'model' => EmployeeDocument::class, 'edit' => 'hr.manage', 'view' => 'hr.manage'],
            'leave_request' => ['label' => 'Leave request', 'model' => LeaveRequest::class, 'edit' => 'hr.manage', 'view' => 'hr.manage'],
            'hr_policy' => ['label' => 'HR policy', 'model' => HrPolicy::class, 'edit' => 'hr.manage'],
        ];
    }

    protected function searchTypes(): array
    {
        return [
            'employee' => ['label' => 'Employee', 'table' => 'employees', 'code' => 'code', 'name' => "first_name || ' ' || coalesce(last_name, '')", 'route' => 'hr.employees.show', 'permission' => 'hr.manage', 'words' => ['emp', 'employee', 'employees', 'staff']],
        ];
    }

    protected function codeKinds(): array
    {
        return [
            'employee' => ['employees', 'EMP', 9, 'Employee'],
            'hr_department' => ['hr_departments', 'DEP', 1, 'Department'],
            'hr_designation' => ['hr_designations', 'DSG', 2, 'Designation'],
            'hr_grade' => ['hr_grades', 'GRD', 3, 'Grade'],
            'hr_location' => ['hr_locations', 'LOC', 4, 'Location'],
            'hr_cost_centre' => ['hr_cost_centres', 'CC', 5, 'Cost centre'],
            'hr_shift' => ['hr_shifts', 'SHF', 7, 'Shift'],
            'leave_request' => ['leave_requests', 'LVR', 8, 'Leave request'],
            'hr_policy' => ['hr_policies', 'POL', 6, 'HR policy'],
            'hr_advance' => ['employee_advances', 'ADV', 9, 'Advance or loan'],
        ];
    }
}
