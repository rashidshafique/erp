<?php

namespace App\Domains\Hr\Http\Controllers;

use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\LeaveLedgerEntry;
use App\Domains\Hr\Models\LeaveRequest;
use App\Domains\Hr\Models\LeaveType;
use App\Domains\Hr\Services\LeaveAccrual;
use App\Domains\Hr\Services\LeaveService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** HR's side of leave: every request, everyone's balance, the leave types and the yearly runs. */
class HrLeaveController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'PENDING');
        $requests = LeaveRequest::with('employee.department', 'type')->when($status !== 'ALL', fn ($q) => $q->where('status', $status))
            ->when($request->filled('employee'), fn ($q) => $q->where('employee_id', $request->employee))
            ->orderByDesc('from_date')->paginate(30)->withQueryString();
        $types = LeaveType::orderBy('name')->get();
        $tracked = $types->filter(fn ($t) => $t->active && $t->tracksBalance())->values();
        $people = Employee::current()->orderBy('code')->get();
        $sums = LeaveLedgerEntry::selectRaw('employee_id, leave_type_id, sum(days) d')->groupBy('employee_id', 'leave_type_id')->get()->groupBy('employee_id');

        return view('hr::leave', compact('requests', 'status', 'types', 'tracked', 'people', 'sums') + ['year' => now()->year, 'pending' => LeaveRequest::where('status', 'PENDING')->count()]);
    }

    private function rules(?string $id = null): array
    {
        return ['code' => ['required', 'string', 'max:12', 'alpha_dash', Rule::unique('leave_types', 'code')->ignore($id)], 'name' => ['required', 'string', 'max:100'], 'annual_days' => ['required', 'numeric', 'min:0', 'max:366'], 'accrual' => ['required', 'in:NONE,MONTHLY,UPFRONT'], 'carry_forward_max' => ['nullable', 'numeric', 'min:0', 'max:366'], 'gender' => ['nullable', 'in:M,F']];
    }

    private function flags(Request $r): array
    {
        return ['paid' => $r->boolean('paid'), 'allow_half_day' => $r->boolean('allow_half_day'), 'counts_days_off' => $r->boolean('counts_days_off'), 'after_probation_only' => $r->boolean('after_probation_only')];
    }

    public function typeStore(Request $request): RedirectResponse
    {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);   // "acme" and "ACME" are the same code
        }
        $d = $request->validate($this->rules());
        LeaveType::create(['code' => strtoupper($d['code']), 'name' => $d['name'], 'annual_days' => $d['annual_days'], 'accrual' => $d['accrual'], 'carry_forward_max' => $d['carry_forward_max'] ?? 0, 'gender' => ($d['gender'] ?? null) ?: null, 'active' => true] + $this->flags($request));

        return redirect()->route('hr.leave', ['tab' => 'types'])->with('success', 'Leave type added.');
    }

    public function typeUpdate(Request $request, LeaveType $type): RedirectResponse
    {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);   // "acme" and "ACME" are the same code
        }
        $d = $request->validate($this->rules($type->id));
        $type->update(['code' => strtoupper($d['code']), 'name' => $d['name'], 'annual_days' => $d['annual_days'], 'accrual' => $d['accrual'], 'carry_forward_max' => $d['carry_forward_max'] ?? 0, 'gender' => ($d['gender'] ?? null) ?: null, 'active' => $request->boolean('active')] + $this->flags($request));

        return redirect()->route('hr.leave', ['tab' => 'types'])->with('success', 'Saved. Balances already on the ledger are not changed.');
    }

    public function accrue(Request $request, LeaveAccrual $acc): RedirectResponse
    {
        $r = $acc->catchUp(Carbon::parse($request->input('as_of', now()->toDateString())));

        return redirect()->route('hr.leave', ['tab' => 'runs'])->with('success', "Entitlements brought up to date: {$r['granted']} yearly grant(s) and {$r['accrued']} monthly accrual(s) added.");
    }

    public function closeYear(Request $request, LeaveAccrual $acc): RedirectResponse
    {
        $y = (int) $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100']])['year'];
        $n = $acc->closeYear($y);

        return redirect()->route('hr.leave', ['tab' => 'runs'])->with('success', $n ? "Year $y closed: $n balance(s) reduced to the carry-forward limit." : "Year $y closed: nothing to expire.");
    }

    public function adjust(Request $request, Employee $employee, LeaveService $svc): RedirectResponse
    {
        $d = $request->validate(['leave_type_id' => ['required', 'uuid', 'exists:leave_types,id'], 'days' => ['required', 'numeric', 'between:-366,366'], 'note' => ['required', 'string', 'max:300']]);
        try {
            $svc->adjust($employee, LeaveType::findOrFail($d['leave_type_id']), (float) $d['days'], $d['note']);
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage());
        }

        return back()->with('success', 'Balance adjusted.');
    }
}
