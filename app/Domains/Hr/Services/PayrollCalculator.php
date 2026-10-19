<?php

namespace App\Domains\Hr\Services;

use App\Domains\Hr\Models\AttendanceRecord;
use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\EmployeeAdvance;
use App\Domains\Hr\Models\EmployeeBenefit;
use App\Domains\Hr\Models\LeaveRequest;
use App\Domains\Hr\Models\PayrollAdjustment;
use App\Models\PayComponent;
use App\Support\Formats;
use Illuminate\Support\Carbon;

/**
 * One person's pay for a date range. It reads the salary structure and the attendance and leave records and
 * writes nothing; the run (or a final settlement) decides what to keep.
 *
 * Pay is worked out like this: a month is worth N days (the days in the month, or a fixed 26 or 30). Days not
 * employed, absent, or on unpaid leave are taken off; each pro-rated line is paid for (N - unpaid) / N of its full amount.
 */
class PayrollCalculator
{
    public function __construct(private SalaryService $salaries, private DayPlan $plan) {}

    /**
     * @param  array{advances?:bool,adjustments?:bool}  $opt
     * @return array<string,mixed>|null null when the person has no salary on file for the range
     */
    public function compute(Employee $e, Carbon $start, Carbon $end, array $opt = []): ?array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();
        $monthStart = $start->copy()->startOfMonth();
        $monthEnd = $start->copy()->endOfMonth()->startOfDay();
        $full = $start->equalTo($monthStart) && $end->equalTo($monthEnd);
        $salary = $this->salaries->at($e, $end);
        if (! $salary) {
            return null;
        }
        $R = PayrollRules::all();
        $rd = fn (float $v) => PayrollRules::round($v);

        $basis = $R['days_basis'] === 'CALENDAR' ? $monthStart->daysInMonth : max(1, (int) $R['days_basis']);

        // ---- the days
        $att = AttendanceRecord::where('employee_id', $e->id)->whereDate('work_date', '>=', $start)->whereDate('work_date', '<=', $end)->get()->keyBy(fn ($r) => $r->work_date->toDateString());
        $leaves = LeaveRequest::with('type')->where('employee_id', $e->id)->where('status', 'APPROVED')->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start)->get();
        $unpaid = 0.0;
        $absent = 0.0;
        $unpaidLeave = 0.0;
        $notEmployed = 0.0;
        $unclosed = 0;
        $lates = 0;
        $otMinutes = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->lt($e->joined_on) || ($e->exit_on && $d->gt($e->exit_on))) {
                $unpaid += 1;
                $notEmployed += 1;

                continue;
            }
            $r = $att->get($d->toDateString());
            $lv = $leaves->first(fn ($l) => $d->betweenIncluded($l->from_date, $l->to_date));
            if (! $r) {
                if ($d->gt(today())) {
                    continue;
                }
                $kind = $this->plan->forDate([$e], $d)[$e->id]['kind'] ?? 'WORK';
                if ($kind === 'WORK') {
                    $unclosed++;
                    if ($R['missing_attendance'] === 'ABSENT') {
                        $unpaid += 1;
                        $absent += 1;
                    }
                }

                continue;
            }
            if ($r->late_minutes > 0) {
                $lates++;
            }
            if ($r->overtime_status === 'APPROVED') {
                $otMinutes += (int) $r->overtime_approved_minutes;
            }
            switch ($r->status) {
                case 'ABSENT':
                    $unpaid += 1;
                    $absent += 1;
                    break;
                case 'LEAVE':
                    if ($lv && ! $lv->type->paid) {
                        $unpaid += 1;
                        $unpaidLeave += 1;
                    }
                    break;
                case 'HALF_DAY':
                    if ($lv && $lv->half_day) {            // half worked, half on leave
                        if (! $lv->type->paid) {
                            $unpaid += 0.5;
                            $unpaidLeave += 0.5;
                        }
                    } elseif ($R['half_day_other_half'] === 'UNPAID') {
                        $unpaid += 0.5;
                        $absent += 0.5;
                    }
                    break;
            }
        }
        $perCut = (int) $R['late_marks_per_day'];
        $lateCut = $perCut > 0 ? intdiv($lates, $perCut) : 0;
        $unpaid += $lateCut;
        $rangeDays = $start->diffInDays($end) + 1;
        $payable = max(0.0, min((float) $basis, ($full ? $basis : $rangeDays) - $unpaid));
        $fraction = $basis > 0 ? $payable / $basis : 0;

        // ---- earnings and fixed deductions from the structure
        $lines = [];
        $sort = 0;
        $basicComp = PayComponent::where('is_basic', true)->first();
        $add = function (string $kind, ?string $code, string $name, float $amt, string $src, bool $taxable = false, ?string $ref = null) use (&$lines, &$sort) {
            $lines[] = ['kind' => $kind, 'code' => $code, 'name' => $name, 'amount' => $amt, 'source' => $src, 'taxable' => $taxable, 'ref_id' => $ref, 'sort' => $sort += 10];
        };
        $fullTaxableRegular = 0.0;
        $grossFull = (float) $salary->basic;
        $add('EARNING', $basicComp?->code ?? 'BASIC', $basicComp?->name ?? 'Basic salary', $rd((float) $salary->basic * $fraction), 'STRUCTURE', true);
        $fullTaxableRegular += (float) $salary->basic;
        foreach ($salary->lines as $l) {
            $c = $l->component;
            $f = $c->prorate ? $fraction : ($payable > 0 ? 1 : 0);
            if ($c->kind === 'DEDUCTION') {
                $add('DEDUCTION', $c->code, $c->name, $rd((float) $l->amount * $f), 'STRUCTURE');
                $grossFull -= (float) $l->amount;
            } else {
                $add('EARNING', $c->code, $c->name, $rd((float) $l->amount * $f), 'STRUCTURE', $c->taxable);
                $grossFull += (float) $l->amount;
                if ($c->taxable) {
                    $fullTaxableRegular += (float) $l->amount;
                }
            }
        }
        // benefits paid in cash
        foreach (EmployeeBenefit::where('employee_id', $e->id)->where('kind', 'CASH')->whereDate('starts_on', '<=', $end)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))->orderBy('name')->get() as $b) {
            $add('EARNING', null, $b->name, $rd((float) $b->monthly_value * ($payable > 0 ? ($b->taxable ? $fraction : 1) : 0)), 'BENEFIT', (bool) $b->taxable, $b->id);
            $grossFull += (float) $b->monthly_value;
            if ($b->taxable) {
                $fullTaxableRegular += (float) $b->monthly_value;
            }
        }
        // overtime
        $otAmount = 0.0;
        if ($otMinutes > 0 && $e->overtime_eligible) {
            $base = $R['overtime_base'] === 'GROSS' ? $grossFull : (float) $salary->basic;
            $hourly = $base / $basis / max(1, (float) $R['hours_per_day']);
            $otAmount = $rd($otMinutes / 60 * $hourly * (float) $R['overtime_multiplier']);
            $add('EARNING', 'OT', 'Overtime ('.intdiv($otMinutes, 60).' h '.($otMinutes % 60).' m × '.rtrim(rtrim(Formats::number((float) $R['overtime_multiplier'], 2), '0'), '.').')', $otAmount, 'OVERTIME', true);
        }
        // one-off items for this month
        $adj = [];
        if ($opt['adjustments'] ?? true) {
            $adj = PayrollAdjustment::where('employee_id', $e->id)->whereDate('month', $monthStart)->whereNull('payslip_id')->orderBy('created_at')->get();
            foreach ($adj as $a) {
                $add($a->kind, null, $a->name, $rd((float) $a->amount), 'ADJUST', $a->kind === 'EARNING' && $a->taxable, $a->id);
            }
        }

        $sum = function (string $kind, ?string $src = null) use (&$lines) {
            return array_sum(array_map(fn ($l) => $l['amount'], array_filter($lines, fn ($l) => $l['kind'] === $kind && ($src === null || $l['source'] === $src))));
        };
        $earnings = $sum('EARNING');
        $variableTaxable = array_sum(array_map(fn ($l) => $l['amount'], array_filter($lines, fn ($l) => $l['kind'] === 'EARNING' && $l['taxable'] && in_array($l['source'], ['OVERTIME', 'ADJUST'], true))));
        $regularTaxable = array_sum(array_map(fn ($l) => $l['amount'], array_filter($lines, fn ($l) => $l['kind'] === 'EARNING' && $l['taxable'] && in_array($l['source'], ['STRUCTURE', 'BENEFIT'], true))));

        // ---- statutory
        $tax = 0.0;
        if ($e->tax_applies && $R['tax_enabled'] && $payable > 0) {
            $annual = $fullTaxableRegular * 12;
            $regularTax = PayrollRules::annualTax($annual) / 12 * $fraction;
            $extra = $variableTaxable > 0 ? PayrollRules::annualTax($annual + $variableTaxable) - PayrollRules::annualTax($annual) : 0.0;
            $tax = $rd($regularTax + $extra);
            if ($tax > 0) {
                $add('DEDUCTION', 'TAX', 'Income tax on salary', $tax, 'TAX');
            }
        }
        $eobiEe = $eobiEr = $ssEr = 0.0;
        if ($payable > 0 && $e->eobi_applies && $R['eobi_enabled']) {
            $eobiEe = $rd((float) $R['eobi_wage_base'] * (float) $R['eobi_employee_percent'] / 100);
            $eobiEr = $rd((float) $R['eobi_wage_base'] * (float) $R['eobi_employer_percent'] / 100);
            if ($eobiEe > 0) {
                $add('DEDUCTION', 'EOBI', 'EOBI contribution (employee)', $eobiEe, 'EOBI');
            }
            if ($eobiEr > 0) {
                $add('EMPLOYER', 'EOBI_ER', 'EOBI contribution (employer)', $eobiEr, 'EOBI');
            }
        }
        if ($payable > 0 && $e->ss_applies && $R['ss_enabled']) {
            $ssEr = $rd(min($sum('EARNING', 'STRUCTURE') + $sum('EARNING', 'BENEFIT'), (float) $R['ss_wage_ceiling']) * (float) $R['ss_employer_percent'] / 100);
            if ($ssEr > 0) {
                $add('EMPLOYER', 'SS_ER', 'Social security (employer)', $ssEr, 'SS');
            }
        }

        // ---- advances and loans: instalments, within the recovery cap
        $recovery = 0.0;
        if (($opt['advances'] ?? true) && $full) {
            $room = max(0.0, $earnings - $sum('DEDUCTION')) * (float) $R['max_recovery_percent'] / 100;
            foreach (EmployeeAdvance::where('employee_id', $e->id)->where('status', 'ACTIVE')->where('paused', false)->whereDate('first_recovery_month', '<=', $monthStart)->orderBy('created_at')->get() as $a) {
                $due = min((float) $a->installment_amount, $a->balance());
                $take = $rd(min($due, $room));
                if ($take > 0) {
                    $add('DEDUCTION', $a->code, ucfirst(strtolower($a->kind)).' recovery '.$a->code, $take, 'ADVANCE', false, $a->id);
                    $room -= $take;
                    $recovery += $take;
                }
            }
        }

        $deductions = $sum('DEDUCTION');
        $net = $earnings - $deductions;

        return [
            'salary_id' => $salary->id, 'basic' => (float) $salary->basic, 'lines' => $lines,
            'period_days' => $basis, 'payable_days' => round($payable, 2), 'unpaid_days' => round($unpaid, 2), 'absent_days' => round($absent, 2), 'unpaid_leave_days' => round($unpaidLeave, 2),
            'late_count' => $lates, 'late_cut_days' => $lateCut, 'overtime_minutes' => $otMinutes, 'not_employed_days' => $notEmployed,
            'gross' => $earnings, 'taxable_income' => $regularTaxable + $variableTaxable, 'income_tax' => $tax, 'eobi_employee' => $eobiEe, 'eobi_employer' => $eobiEr, 'ss_employer' => $ssEr,
            'advance_recovery' => $recovery, 'total_earnings' => $earnings, 'total_deductions' => $deductions, 'net' => $net,
            'flags' => array_values(array_filter([$unclosed ? "UNCLOSED:$unclosed" : null, $net < 0 ? 'NEGATIVE_NET' : null, $payable <= 0 ? 'NO_PAYABLE_DAYS' : null])),
        ];
    }
}
