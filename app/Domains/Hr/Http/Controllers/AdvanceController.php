<?php

namespace App\Domains\Hr\Http\Controllers;

use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\EmployeeAdvance;
use App\Domains\Hr\Services\AdvanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Salary advances and staff loans. */
class AdvanceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'OPEN');
        $rows = EmployeeAdvance::with('employee.department')->when($status === 'OPEN', fn ($q) => $q->whereIn('status', ['PENDING', 'ACTIVE']))->when(! in_array($status, ['OPEN', 'ALL'], true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->whereRaw('lower(first_name || \' \' || coalesce(last_name, \'\')) like ?', ['%'.mb_strtolower($request->input('q')).'%'])->orWhereRaw('lower(code) like ?', ['%'.mb_strtolower($request->input('q')).'%'])))
            ->orderByRaw("case status when 'PENDING' then 0 when 'ACTIVE' then 1 else 2 end")->orderByDesc('created_at')->get();

        return view('hr::payroll.advances.index', ['rows' => $rows, 'status' => $status, 'people' => Employee::current()->orderBy('code')->get(), 'owed' => $rows->where('status', 'ACTIVE')->sum(fn ($a) => $a->balance())]);
    }

    public function store(Request $request, AdvanceService $svc): RedirectResponse
    {
        $d = $request->validate(['employee_id' => ['required', 'uuid', 'exists:employees,id'], 'kind' => ['required', 'in:ADVANCE,LOAN'], 'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'installments' => ['required', 'integer', 'min:1', 'max:120'], 'first_recovery_month' => ['required', 'date_format:Y-m'], 'reason' => ['nullable', 'string', 'max:300']]);
        try {
            $a = $svc->request(Employee::findOrFail($d['employee_id']), $d['kind'], (float) $d['amount'], (int) $d['installments'], $d['first_recovery_month'].'-01', $d['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('hr.payroll.advances.show', $a)->with('success', 'Request recorded. Approve it to pay it out and start recovery.');
    }

    public function show(EmployeeAdvance $advance): View
    {
        return view('hr::payroll.advances.show', ['a' => $advance->load('employee', 'ledger'), 'balance' => $advance->balance()]);
    }

    public function decide(Request $request, EmployeeAdvance $advance, AdvanceService $svc): RedirectResponse
    {
        $d = $request->validate(['decision' => ['required', 'in:APPROVE,REJECT,CANCEL'], 'why' => ['nullable', 'string', 'max:200']]);
        try {
            if ($d['decision'] === 'APPROVE') {
                return $svc->approve($advance, $message) ? back()->with('success', 'Approved and paid out. Recovery starts from '.$advance->first_recovery_month->format('F Y').'.') : back()->with('error', $message);
            }
            $d['decision'] === 'REJECT' ? $svc->reject($advance, $d['why'] ?? null) : $svc->cancel($advance);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $d['decision'] === 'REJECT' ? 'Rejected.' : 'Cancelled.');
    }

    public function pause(EmployeeAdvance $advance): RedirectResponse
    {
        abort_unless($advance->status === 'ACTIVE', 404);
        $advance->update(['paused' => ! $advance->paused]);

        return back()->with('success', $advance->paused ? 'Recovery paused. No instalment is taken until you resume it.' : 'Recovery resumed.');
    }

    public function adjust(Request $request, EmployeeAdvance $advance, AdvanceService $svc): RedirectResponse
    {
        $d = $request->validate(['kind' => ['required', 'in:WAIVER,ADJUST'], 'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'note' => ['required', 'string', 'max:200']]);
        try {
            $svc->adjust($advance, $d['kind'], (float) $d['amount'], $d['note']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $d['kind'] === 'WAIVER' ? 'Written off.' : 'Balance adjusted.');
    }
}
