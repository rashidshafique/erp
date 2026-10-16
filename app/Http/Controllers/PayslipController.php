<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Support\HrAccess;
use Illuminate\View\View;

/** A person's own payslips. Open to anyone signed in, but only ever their own, and only once the payroll is approved. */
class PayslipController extends Controller
{
    public function my(): View
    {
        $me = HrAccess::me();
        $slips = $me ? Payslip::with('period')->where('employee_id', $me->id)->whereHas('period', fn ($q) => $q->whereIn('status', ['APPROVED', 'PAID']))->get()->sortByDesc(fn ($s) => $s->period->month)->values() : collect();

        return view('hr.payroll.my', ['me' => $me, 'slips' => $slips]);
    }

    public function show(Payslip $payslip): View
    {
        $me = HrAccess::me();
        $mine = $me && $payslip->employee_id === $me->id && in_array($payslip->period->status, ['APPROVED', 'PAID'], true);
        abort_unless($mine || HrAccess::isPayroll(), 403);

        return view('hr.payroll.payslip', ['s' => $payslip->load('lines', 'period', 'employee'), 'back' => route('payslips.my')]);
    }
}
