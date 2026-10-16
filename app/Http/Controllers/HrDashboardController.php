<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeEvent;
use App\Models\EmployeeExit;
use App\Models\EmployeeTask;
use App\Models\HrDepartment;
use App\Models\HrPolicy;
use Illuminate\View\View;

class HrDashboardController extends Controller
{
    public function index(): View
    {
        $soon = now()->addDays((int) config('hr.alert_days'))->toDateString();
        $today = now()->toDateString();
        $current = Employee::current();

        $alerts = [
            'probation' => Employee::where('status', 'PROBATION')->whereNotNull('probation_ends_on')->where('probation_ends_on', '<=', $soon)->orderBy('probation_ends_on')->get(),
            'contracts' => Employee::current()->whereNotNull('contract_ends_on')->where('contract_ends_on', '<=', $soon)->orderBy('contract_ends_on')->get(),
            'documents' => EmployeeDocument::with('employee')->whereNotNull('expires_on')->where('expires_on', '<=', $soon)->whereHas('employee', fn ($q) => $q->where('status', '!=', 'EXITED'))->orderBy('expires_on')->get(),
            'tasks' => EmployeeTask::with('employee')->whereNull('done_at')->where('due_on', '<', $today)->whereHas('employee', fn ($q) => $q->where('status', '!=', 'EXITED'))->orderBy('due_on')->limit(15)->get(),
            'exits' => EmployeeExit::with('employee')->where('status', 'INITIATED')->orderBy('last_working_day')->get(),
        ];
        $policyGaps = HrPolicy::where('status', 'PUBLISHED')->where('requires_ack', true)->get()->map(function ($p) {
            $n = Employee::current()->whereNotIn('id', $p->acks()->where('version', $p->version)->select('employee_id'))->count();

            return $n ? ['policy' => $p, 'missing' => $n] : null;
        })->filter()->values();

        $byDept = Employee::current()->selectRaw('department_id, count(*) n')->groupBy('department_id')->pluck('n', 'department_id');
        $depts = HrDepartment::orderBy('name')->get()->map(fn ($d) => ['name' => $d->name, 'n' => (int) ($byDept[$d->id] ?? 0)])->filter(fn ($r) => $r['n'] > 0)->sortByDesc('n')->values();
        $unassigned = (int) ($byDept[''] ?? $byDept[null] ?? 0);

        $month = now()->startOfMonth()->toDateString();
        $stats = [
            'headcount' => $current->count(),
            'onboarding' => Employee::where('status', 'ONBOARDING')->count(),
            'probation' => Employee::where('status', 'PROBATION')->count(),
            'notice' => Employee::where('status', 'NOTICE')->count(),
            'joined' => EmployeeEvent::where('kind', 'JOINED')->where('effective_on', '>=', $month)->count(),
            'left' => EmployeeEvent::where('kind', 'EXITED')->where('effective_on', '>=', $month)->count(),
        ];
        $byType = Employee::current()->selectRaw('employment_type, count(*) n')->groupBy('employment_type')->pluck('n', 'employment_type');
        $recent = EmployeeEvent::with('employee')->orderByDesc('created_at')->limit(10)->get();

        $today2 = \App\Models\AttendanceRecord::whereDate('work_date', $today)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
        $work = [
            'exceptions' => \App\Models\AttendanceRecord::where('exception_status', 'OPEN')->count(),
            'overtime' => \App\Models\AttendanceRecord::where('overtime_status', 'PENDING')->count(),
            'leave' => \App\Models\LeaveRequest::where('status', 'PENDING')->count(),
            'today' => $today2,
        ];

        return view('hr.dashboard', compact('work', 'alerts', 'policyGaps', 'depts', 'unassigned', 'stats', 'byType', 'recent'));
    }
}
