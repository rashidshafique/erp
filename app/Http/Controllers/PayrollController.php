<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payslip;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Monthly payroll: open a month, calculate, check, approve, pay. */
class PayrollController extends Controller
{
    public function index(): View
    {
        return view('hr.payroll.index', ['periods' => PayrollPeriod::orderByDesc('month')->get(), 'next' => (PayrollPeriod::max('month') ? Carbon::parse(PayrollPeriod::max('month'))->addMonth() : now()->subMonth())->startOfMonth()]);
    }

    public function store(Request $request, PayrollService $svc): RedirectResponse
    {
        $d = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        try {
            $p = $svc->createPeriod($d['month'].'-01');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('hr.payroll.show', $p)->with('success', 'Payroll opened for '.$p->month->format('F Y').'. Calculate it when attendance and overtime for the month are settled.');
    }

    public function show(Request $request, PayrollPeriod $period, PayrollService $svc): View
    {
        $q = $period->payslips()->orderBy('employee_code');
        if ($request->filled('q')) {
            $t = '%'.mb_strtolower($request->input('q')).'%';
            $q->where(fn ($w) => $w->whereRaw('lower(employee_name) like ?', [$t])->orWhereRaw('lower(employee_code) like ?', [$t]));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        $all = $period->payslips()->get();
        $depts = $all->groupBy(fn ($s) => $s->department_name ?: 'No department')->map(fn ($g, $k) => ['name' => $k, 'people' => $g->count(), 'gross' => $g->sum('gross'), 'tax' => $g->sum('income_tax'), 'deductions' => $g->sum('total_deductions'), 'net' => $g->sum('net'), 'employer' => $g->sum('eobi_employer') + $g->sum('ss_employer')])->sortKeys()->values();

        return view('hr.payroll.show', [
            'p' => $period, 'slips' => $q->get(), 'checks' => $svc->checks($period), 'depts' => $depts, 'sum' => $period->summary ?? [],
            'adjustments' => PayrollAdjustment::with('employee')->whereDate('month', $period->month)->orderBy('created_at')->get(),
            'people' => Employee::current()->orderBy('code')->get(), 'totals' => ['gross' => $all->sum('gross'), 'net' => $all->sum('net'), 'paid' => $all->where('status', 'PAID')->sum('net'), 'held' => $all->where('status', 'HELD')->count()],
        ]);
    }

    public function calculate(PayrollPeriod $period, PayrollService $svc): RedirectResponse
    {
        try {
            $s = $svc->calculate($period);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('hr.payroll.show', $period)->with('success', 'Calculated '.$s['people'].' payslip'.($s['people'] === 1 ? '' : 's').'.');
    }

    public function approve(PayrollPeriod $period, PayrollService $svc): RedirectResponse
    {
        try {
            $ok = $svc->approve($period, $message);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $ok ? back()->with('success', 'Payroll approved. Instalments have been taken off advances and loans.') : back()->with('error', $message);
    }

    public function revert(PayrollPeriod $period, PayrollService $svc): RedirectResponse
    {
        try {
            $svc->revert($period);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sent back for changes. Recalculate to refresh the payslips.');
    }

    public function pay(Request $request, PayrollPeriod $period, PayrollService $svc): RedirectResponse
    {
        $d = $request->validate(['paid_on' => ['required', 'date'], 'payment_ref' => ['nullable', 'string', 'max:100'], 'ids' => ['nullable', 'array'], 'ids.*' => ['uuid']]);
        try {
            $n = $svc->markPaid($period, $request->filled('ids') ? $d['ids'] : null, $d['paid_on'], $d['payment_ref'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $n.' payslip'.($n === 1 ? '' : 's').' marked paid.');
    }

    public function hold(Request $request, Payslip $payslip, PayrollService $svc): RedirectResponse
    {
        $d = $request->validate(['hold_reason' => ['required', 'string', 'max:200']]);
        try {
            $svc->hold($payslip, $d['hold_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payslip held back.');
    }

    public function release(Payslip $payslip, PayrollService $svc): RedirectResponse
    {
        $svc->release($payslip);

        return back()->with('success', 'Payslip released for payment.');
    }

    public function payslip(Payslip $payslip): View
    {
        return view('hr.payroll.payslip', ['s' => $payslip->load('lines', 'period', 'employee'), 'back' => route('hr.payroll.show', $payslip->period_id)]);
    }

    public function adjustmentStore(Request $request): RedirectResponse
    {
        $d = $request->validate(['employee_id' => ['required', 'uuid', 'exists:employees,id'], 'month' => ['required', 'date_format:Y-m'], 'kind' => ['required', 'in:EARNING,DEDUCTION'], 'name' => ['required', 'string', 'max:120'], 'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'note' => ['nullable', 'string', 'max:300']]);
        $month = Carbon::parse($d['month'].'-01');
        $p = PayrollPeriod::whereDate('month', $month)->first();
        if ($p && in_array($p->status, ['APPROVED', 'PAID'], true)) {
            return back()->with('error', 'That month is already approved. Put the item on the next month, or send the payroll back first.');
        }
        PayrollAdjustment::create(['employee_id' => $d['employee_id'], 'month' => $month, 'kind' => $d['kind'], 'name' => $d['name'], 'amount' => $d['amount'], 'taxable' => $request->boolean('taxable', true), 'note' => $d['note'] ?? null, 'created_by_name' => auth()->user()?->name]);

        return back()->with('success', 'Added. It joins the payslip when the month is calculated.');
    }

    public function adjustmentDestroy(PayrollAdjustment $adjustment): RedirectResponse
    {
        if ($adjustment->payslip_id) {
            return back()->with('error', 'This item was already paid out.');
        }
        $adjustment->delete();

        return back()->with('success', 'Removed.');
    }

    /** CSV of every payslip line: the payroll register. */
    public function register(PayrollPeriod $period): StreamedResponse
    {
        return $this->csv('payroll-'.$period->month->format('Y-m').'.csv', ['Code', 'Name', 'Department', 'Basic', 'Payable days', 'Unpaid days', 'Overtime min', 'Gross', 'Income tax', 'EOBI (employee)', 'Advance recovery', 'Total deductions', 'Net', 'EOBI (employer)', 'Social security (employer)', 'Status'],
            $period->payslips()->orderBy('employee_code')->get()->map(fn ($s) => [$s->employee_code, $s->employee_name, $s->department_name, $s->basic, $s->payable_days, $s->unpaid_days, $s->overtime_minutes, $s->gross, $s->income_tax, $s->eobi_employee, $s->advance_recovery, $s->total_deductions, $s->net, $s->eobi_employer, $s->ss_employer, $s->status])->all());
    }

    /** CSV for the bank: only people paid by transfer who are not held. */
    public function bankFile(PayrollPeriod $period): StreamedResponse
    {
        return $this->csv('bank-advice-'.$period->month->format('Y-m').'.csv', ['Code', 'Name', 'Bank', 'Account', 'Amount', 'Narration'],
            $period->payslips()->where('pay_mode', 'BANK')->whereIn('status', ['UNPAID'])->where('net', '>', 0)->orderBy('employee_code')->get()->map(fn ($s) => [$s->employee_code, $s->employee_name, $s->bank_name, "\t".$s->bank_account_no, $s->net, 'Salary '.$period->month->format('M Y')])->all());
    }

    public static function csvResponse(string $name, array $head, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($head, $rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $head);
            foreach ($rows as $r) {
                fputcsv($f, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@-]/', $v) ? "'".$v : $v, $r));
            }
            fclose($f);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csv(string $name, array $head, array $rows): StreamedResponse
    {
        return self::csvResponse($name, $head, $rows);
    }
}
