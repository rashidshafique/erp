<?php

namespace App\Domains\Hr\Http\Controllers;

use App\Domains\Hr\Models\AttendanceRecord;
use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\EmployeeAdvance;
use App\Domains\Hr\Models\EmployeeBenefit;
use App\Domains\Hr\Models\HrCostCentre;
use App\Domains\Hr\Models\HrDepartment;
use App\Domains\Hr\Models\HrDesignation;
use App\Domains\Hr\Models\HrGrade;
use App\Domains\Hr\Models\HrLocation;
use App\Domains\Hr\Models\HrPolicy;
use App\Domains\Hr\Models\HrShift;
use App\Domains\Hr\Models\LeaveLedgerEntry;
use App\Domains\Hr\Models\LeaveRequest;
use App\Domains\Hr\Models\LeaveType;
use App\Domains\Hr\Models\PayrollAdjustment;
use App\Domains\Hr\Models\Payslip;
use App\Domains\Hr\Services\AttendanceService;
use App\Domains\Hr\Services\LeaveService;
use App\Domains\Hr\Services\Lifecycle;
use App\Domains\Hr\Services\SalaryService;
use App\Domains\Hr\Support\HrAccess;
use App\Http\Controllers\Controller;
use App\Models\PayComponent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class EmployeeController extends Controller
{
    private function lookups(): array
    {
        return [
            'departments' => HrDepartment::where('active', true)->orderBy('name')->get(),
            'designations' => HrDesignation::where('active', true)->orderBy('name')->get(),
            'grades' => HrGrade::where('active', true)->orderBy('rank')->get(),
            'locations' => HrLocation::where('active', true)->orderBy('name')->get(),
            'costCentres' => HrCostCentre::where('active', true)->orderBy('code')->get(),
            'managers' => Employee::current()->orderBy('first_name')->get(),
            'shifts' => HrShift::where('active', true)->orderBy('starts_at')->get(),
        ];
    }

    public function index(Request $request): View
    {
        $q = Employee::query()->with('department', 'designation', 'location')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.trim($request->search).'%';
                $q->where(fn ($w) => $w->where('code', 'ilike', $s)->orWhere('first_name', 'ilike', $s)->orWhere('last_name', 'ilike', $s)->orWhere('cnic', 'ilike', $s)->orWhere('phone', 'ilike', $s));
            })
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->department))
            ->when($request->filled('location'), fn ($q) => $q->where('location_id', $request->location))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status), fn ($q) => $q->where('status', '!=', 'EXITED'));

        return view('hr::employees.index', $this->lookups() + ['employees' => $q->orderBy('code')->paginate(25)->withQueryString()]);
    }

    public function create(): View
    {
        return view('hr::employees.create', $this->lookups());
    }

    private function rules(?Employee $e = null): array
    {
        $id = $e?->id;

        return [
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['nullable', 'string', 'max:80'], 'father_name' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(array_keys(config('hr.genders')))], 'date_of_birth' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', Rule::in(array_keys(config('hr.marital')))],
            'cnic' => ['nullable', 'regex:/^\d{5}-?\d{7}-?\d$/', Rule::unique('employees', 'cnic')->ignore($id)],
            'nationality' => ['nullable', 'string', 'max:60'], 'blood_group' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:40'], 'personal_email' => ['nullable', 'email', 'max:160'], 'work_email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:400'], 'city' => ['nullable', 'string', 'max:80'],
            'emergency_name' => ['nullable', 'string', 'max:120'], 'emergency_relation' => ['nullable', 'string', 'max:60'], 'emergency_phone' => ['nullable', 'string', 'max:40'],
            'department_id' => ['nullable', 'uuid', 'exists:hr_departments,id'], 'designation_id' => ['nullable', 'uuid', 'exists:hr_designations,id'],
            'grade_id' => ['nullable', 'uuid', 'exists:hr_grades,id'], 'location_id' => ['nullable', 'uuid', 'exists:hr_locations,id'],
            'cost_centre_id' => ['nullable', 'uuid', 'exists:hr_cost_centres,id'], 'manager_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'employment_type' => ['required', Rule::in(array_keys(config('hr.employment_types')))],
            'shift_id' => ['nullable', 'uuid', 'exists:hr_shifts,id'], 'weekly_off' => ['nullable', Rule::in(array_keys(config('hr.weekly_off_options')))],
            'joined_on' => ['required', 'date', 'before_or_equal:+1 year'], 'probation_ends_on' => ['nullable', 'date', 'after_or_equal:joined_on'],
            'contract_ends_on' => ['nullable', 'date', 'after:joined_on'],
            'bank_name' => ['nullable', 'string', 'max:100'], 'bank_account_no' => ['nullable', 'string', 'max:60'], 'ntn' => ['nullable', 'string', 'max:30'],
            'eobi_no' => ['nullable', 'string', 'max:30'], 'social_security_no' => ['nullable', 'string', 'max:30'], 'notes' => ['nullable', 'string', 'max:3000'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($id)],
        ];
    }

    private function clean(array $d): array
    {
        foreach ($d as $k => $v) {
            if ($v === '') {
                $d[$k] = null;
            }
        }
        if (($d['weekly_off'] ?? null) === 'NONE') {
            $d['weekly_off'] = '';                       // "none, the roster decides"
        }
        if (! empty($d['cnic'])) {
            $digits = preg_replace('/\D/', '', $d['cnic']);
            $d['cnic'] = substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12, 1);
        }

        return $d;
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->clean($request->validate($this->rules()));
        $d['status'] = 'ONBOARDING';
        if (empty($d['probation_ends_on']) && $d['employment_type'] === 'PERMANENT') {
            $d['probation_ends_on'] = Carbon::parse($d['joined_on'])->addMonths((int) config('hr.probation_months'))->toDateString();
        }
        if ($d['employment_type'] !== 'PERMANENT') {
            $d['probation_ends_on'] = null;
        }
        $e = Employee::create($d);
        app(Lifecycle::class)->joined($e->load('designation'));

        return redirect()->route('hr.employees.show', $e)->with('success', "{$e->code} {$e->full_name} added. The joining checklist is ready.");
    }

    public function show(Request $request, Employee $employee, AttendanceService $att, LeaveService $leave): View
    {
        $month = Carbon::parse($request->input('month', now()->format('Y-m').'-01'))->startOfMonth();
        $from = $month->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();
        $employee->load('department.parent', 'designation', 'grade', 'location', 'costCentre', 'manager', 'user', 'reports.designation', 'events', 'documents', 'tasks');
        $policies = HrPolicy::where('status', 'PUBLISHED')->where('requires_ack', true)->with(['acks' => fn ($q) => $q->where('employee_id', $employee->id)])->orderBy('title')->get();

        return view('hr::employees.show', $this->lookups() + [
            'e' => $employee, 'policies' => $policies, 'openExit' => $employee->openExit(),
            'month' => $month, 'attRows' => AttendanceRecord::where('employee_id', $employee->id)->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->orderBy('work_date')->get()->keyBy(fn ($r) => $r->work_date->toDateString()),
            'attSummary' => $att->summary($employee, $from, $to), 'leaveBalances' => $leave->balances($employee), 'leaveTypes' => LeaveType::where('active', true)->orderBy('name')->get(),
            'leaveRequests' => LeaveRequest::with('type')->where('employee_id', $employee->id)->orderByDesc('from_date')->limit(25)->get(),
            'ledger' => LeaveLedgerEntry::with('type')->where('employee_id', $employee->id)->orderByDesc('entry_date')->orderByDesc('created_at')->limit(30)->get(),
            'pay' => HrAccess::isPayroll() ? $this->payData($employee) : null,
            'users' => User::where('is_active', true)->whereNotIn('id', Employee::whereNotNull('user_id')->where('id', '!=', $employee->id)->pluck('user_id'))->orderBy('name')->get(),
        ]);
    }

    /** Everything the Pay tab shows. Only built for people allowed to see salaries. */
    private function payData(Employee $e): array
    {
        $svc = app(SalaryService::class);
        $adv = EmployeeAdvance::where('employee_id', $e->id)->orderByDesc('created_at')->get();

        return [
            'salaries' => $svc->history($e), 'current' => $svc->at($e, today()), 'components' => PayComponent::where('active', true)->where('is_basic', false)->orderBy('sort')->get(),
            'benefits' => EmployeeBenefit::where('employee_id', $e->id)->orderByDesc('starts_on')->get(), 'advances' => $adv,
            'owed' => $adv->where('status', 'ACTIVE')->sum(fn ($a) => $a->balance()),
            'slips' => Payslip::with('period')->where('employee_id', $e->id)->get()->sortByDesc(fn ($s) => $s->period->month)->take(12)->values(),
            'adjustments' => PayrollAdjustment::where('employee_id', $e->id)->orderByDesc('month')->limit(15)->get(),
        ];
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $rules = $this->rules($employee);
        // Position changes go through Transfer so they are dated, approved and recorded.
        foreach (['department_id', 'designation_id', 'grade_id', 'location_id', 'cost_centre_id', 'manager_id'] as $k) {
            unset($rules[$k]);
        }
        $d = $this->clean($request->validate($rules));
        $employee->update($d);

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Saved.');
    }

    public function startWork(Employee $employee, Lifecycle $life): RedirectResponse
    {
        try {
            $open = $employee->tasks()->where('kind', 'ONBOARDING')->whereNull('done_at')->count();
            $life->startWork($employee);
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage());
        }

        return back()->with('success', $open ? "Onboarding closed ({$open} task".($open === 1 ? '' : 's').' still open).' : 'Onboarding finished.');
    }

    public function confirm(Request $request, Employee $employee, Lifecycle $life): RedirectResponse
    {
        $d = $request->validate(['confirmed_on' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:500']]);
        try {
            $ok = $life->confirm($employee, $d['confirmed_on'], $d['note'] ?? null, $msg);
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage());
        }

        return $ok ? back()->with('success', 'Confirmed.') : back()->with('error', $msg);
    }

    public function transfer(Request $request, Employee $employee, Lifecycle $life): RedirectResponse
    {
        $d = $request->validate([
            'kind' => ['required', 'in:TRANSFER,PROMOTION'], 'effective_on' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:500'],
            'department_id' => ['nullable', 'uuid', 'exists:hr_departments,id'], 'designation_id' => ['nullable', 'uuid', 'exists:hr_designations,id'],
            'grade_id' => ['nullable', 'uuid', 'exists:hr_grades,id'], 'location_id' => ['nullable', 'uuid', 'exists:hr_locations,id'],
            'cost_centre_id' => ['nullable', 'uuid', 'exists:hr_cost_centres,id'], 'manager_id' => ['nullable', 'uuid', 'exists:employees,id'],
        ]);
        $to = array_intersect_key($d, Lifecycle::TRACKED);
        try {
            $ok = $life->transfer($employee, $to, $d['effective_on'], $d['kind'], $d['note'] ?? null, $msg);
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage())->withInput();
        }

        return $ok ? back()->with('success', $d['kind'] === 'PROMOTION' ? 'Promotion recorded.' : 'Transfer recorded.') : back()->with('error', $msg);
    }

    public function startExit(Request $request, Employee $employee, Lifecycle $life): RedirectResponse
    {
        $d = $request->validate([
            'exit_type' => ['required', Rule::in(array_keys(config('hr.exit_types')))], 'notice_given_on' => ['nullable', 'date'],
            'last_working_day' => ['required', 'date', 'after_or_equal:'.$employee->joined_on->toDateString()], 'reason' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $x = $life->startExit($employee, $d);
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage())->withInput();
        }

        return redirect()->route('hr.exits.show', $x)->with('success', 'Exit started. Work through the clearance tasks, then complete it.');
    }
}
