<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** What was deducted for tax, EOBI and social security: by month or for a tax year (July to June). */
class StatutoryController extends Controller
{
    private function range(Request $r): array
    {
        $today = now();
        $fyStart = $today->month >= 7 ? $today->year : $today->year - 1;
        if ($r->input('scope') === 'year') {
            $y = (int) $r->input('year', $fyStart);

            return [Carbon::create($y, 7, 1)->startOfDay(), Carbon::create($y + 1, 6, 30)->startOfDay(), "Tax year July $y – June ".($y + 1), $y];
        }
        $m = Carbon::parse($r->input('month', now()->subMonth()->format('Y-m').'-01'))->startOfMonth();

        return [$m, $m->copy()->endOfMonth()->startOfDay(), $m->format('F Y'), $fyStart];
    }

    private function rows(Carbon $from, Carbon $to)
    {
        $periods = PayrollPeriod::whereIn('status', ['APPROVED', 'PAID'])->whereDate('month', '>=', $from)->whereDate('month', '<=', $to)->pluck('id');

        return Payslip::with('employee')->whereIn('period_id', $periods)->get()->groupBy('employee_id')->map(function ($g) {
            $e = $g->first()->employee;

            return ['code' => $g->first()->employee_code, 'name' => $g->first()->employee_name, 'cnic' => $e?->cnic, 'ntn' => $e?->ntn, 'eobi_no' => $e?->eobi_no, 'ss_no' => $e?->social_security_no, 'months' => $g->count(),
                'gross' => $g->sum('gross'), 'taxable' => $g->sum('taxable_income'), 'tax' => $g->sum('income_tax'), 'eobi_ee' => $g->sum('eobi_employee'), 'eobi_er' => $g->sum('eobi_employer'), 'ss_er' => $g->sum('ss_employer')];
        })->sortBy('code')->values();
    }

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        [$from, $to, $label, $year] = $this->range($request);
        $rows = $this->rows($from, $to);
        if ($request->input('csv')) {
            return PayrollController::csvResponse('statutory-'.$from->format('Y-m').'.csv', ['Code', 'Name', 'CNIC', 'NTN', 'EOBI no', 'Social security no', 'Months', 'Gross pay', 'Taxable income', 'Income tax deducted', 'EOBI (employee)', 'EOBI (employer)', 'Social security (employer)'],
                $rows->map(fn ($r) => [$r['code'], $r['name'], $r['cnic'], $r['ntn'], $r['eobi_no'], $r['ss_no'], $r['months'], $r['gross'], $r['taxable'], $r['tax'], $r['eobi_ee'], $r['eobi_er'], $r['ss_er']])->all());
        }

        return view('hr.payroll.statutory', ['rows' => $rows, 'label' => $label, 'scope' => $request->input('scope') === 'year' ? 'year' : 'month', 'month' => $from->format('Y-m'), 'year' => $year,
            'missing' => $rows->filter(fn ($r) => ($r['tax'] > 0 && ! $r['ntn']) || ($r['eobi_ee'] > 0 && ! $r['eobi_no']))->count()]);
    }
}
