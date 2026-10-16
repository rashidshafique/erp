<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayComponent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Salary structures: a dated basic plus allowances and fixed deductions. A new revision never overwrites an old one. */
class SalaryService
{
    public function at(Employee $e, $date): ?EmployeeSalary
    {
        return EmployeeSalary::with('lines.component')->where('employee_id', $e->id)->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->first();
    }

    public function history(Employee $e)
    {
        return EmployeeSalary::with('lines.component')->where('employee_id', $e->id)->orderByDesc('effective_from')->get();
    }

    /** What an allowance comes to by default for a given basic. */
    public function defaultAmount(PayComponent $c, float $basic): float
    {
        return $c->calc === 'PERCENT_BASIC' ? round($basic * $c->default_value / 100, 2) : (float) $c->default_value;
    }

    /**
     * @param  array<string,mixed>  $amounts  component id => amount; blank takes the component's default, 0 leaves it out
     * @return array{salary:EmployeeSalary,warnings:array<int,string>}
     */
    public function revise(Employee $e, string $from, float $basic, array $amounts, ?string $reason): array
    {
        if ($basic <= 0) {
            throw new RuntimeException('The basic salary must be more than zero.');
        }
        $warnings = [];
        $salary = DB::transaction(function () use ($e, $from, $basic, $amounts, $reason) {
            $s = EmployeeSalary::updateOrCreate(['employee_id' => $e->id, 'effective_from' => $from], ['basic' => $basic, 'reason' => $reason, 'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name]);
            $s->lines()->delete();
            foreach (PayComponent::where('active', true)->where('is_basic', false)->orderBy('sort')->get() as $c) {
                $raw = $amounts[$c->id] ?? null;
                $v = ($raw === null || $raw === '') ? $this->defaultAmount($c, $basic) : (float) $raw;
                if ($v > 0) {
                    $s->lines()->create(['component_id' => $c->id, 'amount' => $v]);
                }
            }

            return $s->load('lines.component');
        });
        $g = $e->grade;
        if ($g && $g->min_salary && $basic < (float) $g->min_salary) {
            $warnings[] = 'The basic is below the '.$g->name.' band (minimum '.number_format($g->min_salary).').';
        }
        if ($g && $g->max_salary && $basic > (float) $g->max_salary) {
            $warnings[] = 'The basic is above the '.$g->name.' band (maximum '.number_format($g->max_salary).').';
        }

        return ['salary' => $salary, 'warnings' => $warnings];
    }
}
