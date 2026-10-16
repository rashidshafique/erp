<?php

namespace App\Http\Controllers;

use App\Models\PayComponent;
use App\Services\Payroll\PayrollRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Pay components and the payroll rules (tax slabs, EOBI, social security, overtime, recovery caps). */
class PayrollSetupController extends Controller
{
    public function index(): View
    {
        return view('hr.payroll.setup', ['components' => PayComponent::orderBy('sort')->get(), 'R' => PayrollRules::all()]);
    }

    private function rules(?string $id = null): array
    {
        return ['code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('pay_components', 'code')->ignore($id)], 'name' => ['required', 'string', 'max:100'], 'kind' => ['required', 'in:EARNING,DEDUCTION'], 'calc' => ['required', 'in:FIXED,PERCENT_BASIC'], 'default_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'sort' => ['nullable', 'integer', 'min:0', 'max:999']];
    }

    public function componentStore(Request $request): RedirectResponse
    {
        $d = $request->validate($this->rules());
        PayComponent::create(['code' => strtoupper($d['code']), 'name' => $d['name'], 'kind' => $d['kind'], 'calc' => $d['calc'], 'default_value' => $d['default_value'] ?? 0, 'sort' => $d['sort'] ?? 50, 'taxable' => $request->boolean('taxable'), 'prorate' => $request->boolean('prorate'), 'active' => true]);

        return redirect()->route('hr.payroll.setup', ['tab' => 'components'])->with('success', 'Component added. It appears on the salary form for new revisions.');
    }

    public function componentUpdate(Request $request, PayComponent $component): RedirectResponse
    {
        $d = $request->validate($this->rules($component->id));
        $component->update(['code' => $component->is_basic ? $component->code : strtoupper($d['code']), 'name' => $d['name'], 'kind' => $component->is_basic ? 'EARNING' : $d['kind'], 'calc' => $component->is_basic ? 'FIXED' : $d['calc'], 'default_value' => $d['default_value'] ?? 0, 'sort' => $d['sort'] ?? $component->sort, 'taxable' => $request->boolean('taxable'), 'prorate' => $request->boolean('prorate'), 'active' => $component->is_basic ? true : $request->boolean('active')]);

        return redirect()->route('hr.payroll.setup', ['tab' => 'components'])->with('success', 'Saved. Salaries already set keep the amounts they have.');
    }

    public function rulesUpdate(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'days_basis' => ['required', 'regex:/^(CALENDAR|\d{2})$/'], 'missing_attendance' => ['required', 'in:PRESENT,ABSENT'], 'late_marks_per_day' => ['required', 'integer', 'min:0', 'max:31'], 'half_day_other_half' => ['required', 'in:PAID,UNPAID'],
            'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:5'], 'overtime_base' => ['required', 'in:BASIC,GROSS'], 'hours_per_day' => ['required', 'numeric', 'min:1', 'max:24'], 'round_to' => ['required', 'in:1,0.01'],
            'max_recovery_percent' => ['required', 'numeric', 'min:0', 'max:100'], 'tax_surcharge_over' => ['nullable', 'numeric', 'min:0'], 'tax_surcharge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'eobi_wage_base' => ['required', 'numeric', 'min:0'], 'eobi_employee_percent' => ['required', 'numeric', 'min:0', 'max:100'], 'eobi_employer_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'ss_employer_percent' => ['required', 'numeric', 'min:0', 'max:100'], 'ss_wage_ceiling' => ['required', 'numeric', 'min:0'],
            'gratuity_days_per_year' => ['required', 'numeric', 'min:0', 'max:365'], 'gratuity_min_years' => ['required', 'integer', 'min:0', 'max:50'], 'encash_leave_codes' => ['nullable', 'string', 'max:200'], 'tax_year_note' => ['nullable', 'string', 'max:200'],
            'tax_slabs' => ['required', 'string', 'max:2000'],
        ]);
        $slabs = [];
        foreach (preg_split('/\R/', trim($d['tax_slabs'])) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $bits = array_map(fn ($x) => trim(str_replace([',', '%'], '', $x)), preg_split('/[;|\t]+|\s{2,}|\s*:\s*/', trim($line)));
            if (count($bits) !== 3 || ! is_numeric($bits[0]) || ! is_numeric($bits[1]) || ! is_numeric($bits[2])) {
                return back()->withInput()->withErrors(['tax_slabs' => 'Each slab line must read: starts at ; rate % ; tax at the start. For example  600000 ; 1 ; 0']);
            }
            $slabs[] = ['from' => (float) $bits[0], 'rate' => (float) $bits[1], 'fixed' => (float) $bits[2]];
        }
        usort($slabs, fn ($a, $b) => $a['from'] <=> $b['from']);
        if (! $slabs || $slabs[0]['from'] != 0) {
            return back()->withInput()->withErrors(['tax_slabs' => 'The first slab must start at 0.']);
        }
        $R = $d;
        $R['tax_slabs'] = $slabs;
        $R['round_to'] = (float) $d['round_to'];
        $R['encash_leave_codes'] = array_values(array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', (string) ($d['encash_leave_codes'] ?? '')))));
        foreach (['tax_enabled', 'eobi_enabled', 'ss_enabled', 'gratuity_enabled'] as $k) {
            $R[$k] = $request->boolean($k);
        }
        $R['tax_surcharge_over'] = (float) ($d['tax_surcharge_over'] ?? 0);
        $R['tax_surcharge_percent'] = (float) ($d['tax_surcharge_percent'] ?? 0);
        PayrollRules::save($R + PayrollRules::all());

        return redirect()->route('hr.payroll.setup', ['tab' => 'rules'])->with('success', 'Rules saved. They apply the next time a payroll is calculated.');
    }
}
