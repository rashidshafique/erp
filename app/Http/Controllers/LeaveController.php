<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Hr\LeaveService;
use App\Support\HrAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/** Leave as an employee and as a manager. Open to everyone signed in; what each person may do is decided here. */
class LeaveController extends Controller
{
    public function my(LeaveService $svc): View
    {
        $me = HrAccess::me();
        $isHr = HrAccess::isHr();
        $mine = $me ? LeaveRequest::with('type')->where('employee_id', $me->id)->orderByDesc('from_date')->limit(30)->get() : collect();
        $waiting = LeaveRequest::with('employee', 'type')->where('status', 'PENDING')->orderBy('from_date')->get()->filter(fn ($r) => HrAccess::canDecide($r))->values();
        $team = $isHr ? collect() : ($me ? Employee::current()->with('designation')->get()->filter(fn ($e) => $e->id !== $me->id && HrAccess::managesEmployee($e))->values() : collect());

        return view('leave.my', [
            'me' => $me, 'mine' => $mine, 'waiting' => $waiting, 'team' => $team, 'isHr' => $isHr,
            'balances' => $me ? $svc->balances($me) : collect(),
            'types' => LeaveType::where('active', true)->orderBy('name')->get(),
            'people' => $isHr ? Employee::current()->orderBy('code')->get() : collect(),
        ]);
    }

    public function store(Request $request, LeaveService $svc): RedirectResponse
    {
        $d = $request->validate(['employee_id' => ['nullable', 'uuid', 'exists:employees,id'], 'leave_type_id' => ['required', 'uuid', 'exists:leave_types,id'], 'from_date' => ['required', 'date'], 'to_date' => ['nullable', 'date', 'after_or_equal:from_date'], 'half_day' => ['nullable', 'boolean'], 'reason' => ['nullable', 'string', 'max:500'], 'approved_now' => ['nullable', 'boolean']]);
        $e = ! empty($d['employee_id']) ? Employee::findOrFail($d['employee_id']) : HrAccess::me();
        if (! $e) {
            return back()->with('error', 'Your login is not linked to an employee record yet. Ask HR to link it.');
        }
        if ($e->id !== HrAccess::me()?->id && ! HrAccess::isHr()) {
            abort(403, 'You can only ask for leave for yourself.');
        }
        $type = LeaveType::findOrFail($d['leave_type_id']);
        $to = $d['to_date'] ?? $d['from_date'];
        try {
            if ($request->boolean('approved_now') && HrAccess::isHr()) {
                $r = $svc->fileAndApprove($e, $type, $d['from_date'], $to, $request->boolean('half_day'), $d['reason'] ?? null);

                return back()->with('success', "{$r->code} recorded and approved: ".rtrim(rtrim(number_format($r->days, 2), '0'), '.').' day(s).');
            }
            $r = $svc->request($e, $type, $d['from_date'], $to, $request->boolean('half_day'), $d['reason'] ?? null);
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage())->withInput();
        }

        return back()->with('success', "{$r->code} sent for approval: ".rtrim(rtrim(number_format($r->days, 2), '0'), '.').' day(s).');
    }

    public function decide(Request $request, LeaveRequest $leave, LeaveService $svc): RedirectResponse
    {
        abort_unless(HrAccess::canDecide($leave), 403, 'Only HR or the manager of this person can decide this.');
        $d = $request->validate(['decision' => ['required', 'in:APPROVE,REJECT'], 'note' => ['nullable', 'string', 'max:300']]);
        try {
            $d['decision'] === 'APPROVE' ? $svc->approve($leave, $d['note'] ?? null) : $svc->reject($leave, $d['note'] ?? null);
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage());
        }

        return back()->with('success', $leave->code.($d['decision'] === 'APPROVE' ? ' approved.' : ' rejected.'));
    }

    public function cancel(Request $request, LeaveRequest $leave, LeaveService $svc): RedirectResponse
    {
        $own = $leave->employee_id === HrAccess::me()?->id || $leave->requested_by_id === Auth::id();
        abort_unless(HrAccess::isHr() || ($own && $leave->status === 'PENDING') || ($own && $leave->status === 'APPROVED' && $leave->from_date->isFuture()) || HrAccess::canDecide($leave), 403, 'You cannot cancel this.');
        try {
            $svc->cancel($leave, $request->input('note'));
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage());
        }

        return back()->with('success', $leave->code.' cancelled.');
    }
}
