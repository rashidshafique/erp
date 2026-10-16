<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeBenefit;
use App\Services\Payroll\SalaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** An employee's pay: the salary structure, benefits and the pay profile (how they are paid, and which deductions apply). */
class SalaryController extends Controller
{
    public function revise(Request $request, Employee $employee, SalaryService $svc): RedirectResponse
    {
        $d = $request->validate(['effective_from' => ['required', 'date'], 'basic' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'amounts' => ['nullable', 'array'], 'amounts.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'reason' => ['nullable', 'string', 'max:200']]);
        try {
            $r = $svc->revise($employee, $d['effective_from'], (float) $d['basic'], $d['amounts'] ?? [], $d['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Salary saved'.($r['warnings'] ? ' — '.implode(' ', $r['warnings']) : '.'));
    }

    public function profile(Request $request, Employee $employee): RedirectResponse
    {
        $d = $request->validate(['pay_mode' => ['required', 'in:BANK,CASH,CHEQUE']]);
        $employee->update(['pay_mode' => $d['pay_mode'], 'tax_applies' => $request->boolean('tax_applies'), 'eobi_applies' => $request->boolean('eobi_applies'), 'ss_applies' => $request->boolean('ss_applies'), 'overtime_eligible' => $request->boolean('overtime_eligible')]);

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Pay profile saved.');
    }

    public function benefitStore(Request $request, Employee $employee): RedirectResponse
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'kind' => ['required', 'in:CASH,IN_KIND'], 'monthly_value' => ['required', 'numeric', 'min:0', 'max:99999999'], 'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'note' => ['nullable', 'string', 'max:300']]);
        EmployeeBenefit::create($d + ['employee_id' => $employee->id, 'taxable' => $request->boolean('taxable', true)]);

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Benefit added.');
    }

    public function benefitDestroy(Employee $employee, EmployeeBenefit $benefit): RedirectResponse
    {
        abort_unless($benefit->employee_id === $employee->id, 404);
        $benefit->delete();

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Benefit removed.');
    }
}
