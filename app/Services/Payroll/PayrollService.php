<?php

namespace App\Services\Payroll;

use App\Models\AdvanceLedgerEntry;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeSettlement;
use App\Models\Payslip;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Services\Approvals\ApprovalGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** A month's payroll: open the period, calculate everyone, approve, pay. Approval is the point of no return. */
class PayrollService
{
    public function __construct(private PayrollCalculator $calc)
    {
    }

    public function createPeriod(string $month): PayrollPeriod
    {
        $m = Carbon::parse($month)->startOfMonth();
        if (PayrollPeriod::whereDate('month', $m)->exists()) {
            throw new RuntimeException('There is already a payroll for '.$m->format('F Y').'.');
        }

        return PayrollPeriod::create(['code' => 'PAY-'.$m->format('Y-m'), 'month' => $m, 'starts_on' => $m, 'ends_on' => $m->copy()->endOfMonth()->startOfDay(), 'status' => 'DRAFT']);
    }

    /** Everyone who was employed at some point in the month, apart from people paid through a final settlement. */
    public function people(PayrollPeriod $p)
    {
        $settled = EmployeeSettlement::pluck('employee_id')->all();

        return Employee::query()->whereDate('joined_on', '<=', $p->ends_on)->where(fn ($q) => $q->whereNull('exit_on')->orWhereDate('exit_on', '>=', $p->starts_on))
            ->whereNotIn('id', $settled)->with('department', 'costCentre', 'designation')->orderBy('code')->get();
    }

    public function calculate(PayrollPeriod $p): array
    {
        if (! in_array($p->status, ['DRAFT', 'CALCULATED'], true)) {
            throw new RuntimeException('This payroll is already approved. Revert the approval before changing it.');
        }
        $held = Payslip::where('period_id', $p->id)->where('status', 'HELD')->pluck('hold_reason', 'employee_id')->all();
        $sum = ['people' => 0, 'gross' => 0.0, 'net' => 0.0, 'tax' => 0.0, 'eobi_employee' => 0.0, 'eobi_employer' => 0.0, 'ss_employer' => 0.0, 'advances' => 0.0, 'overtime_minutes' => 0, 'no_salary' => [], 'settled' => EmployeeSettlement::with('employee')->get()->map(fn ($s) => $s->employee?->code)->filter()->values()->all(), 'unclosed' => 0, 'negative' => 0];

        DB::transaction(function () use ($p, $held, &$sum) {
            Payslip::where('period_id', $p->id)->delete();
            foreach ($this->people($p) as $e) {
                $r = $this->calc->compute($e, $p->starts_on, $p->ends_on);
                if (! $r) {
                    $sum['no_salary'][] = $e->code.' '.$e->full_name;
                    continue;
                }
                $slip = Payslip::create([
                    'period_id' => $p->id, 'employee_id' => $e->id, 'employee_code' => $e->code, 'employee_name' => $e->full_name,
                    'department_id' => $e->department_id, 'department_name' => $e->department?->name, 'cost_centre_id' => $e->cost_centre_id, 'cost_centre_name' => $e->costCentre?->name, 'designation_name' => $e->designation?->name,
                    'basic' => $r['basic'], 'period_days' => $r['period_days'], 'payable_days' => $r['payable_days'], 'unpaid_days' => $r['unpaid_days'], 'absent_days' => $r['absent_days'], 'unpaid_leave_days' => $r['unpaid_leave_days'],
                    'late_count' => $r['late_count'], 'overtime_minutes' => $r['overtime_minutes'], 'gross' => $r['gross'], 'taxable_income' => $r['taxable_income'], 'income_tax' => $r['income_tax'],
                    'eobi_employee' => $r['eobi_employee'], 'eobi_employer' => $r['eobi_employer'], 'ss_employer' => $r['ss_employer'], 'advance_recovery' => $r['advance_recovery'],
                    'total_earnings' => $r['total_earnings'], 'total_deductions' => $r['total_deductions'], 'net' => $r['net'],
                    'pay_mode' => $e->pay_mode ?: 'BANK', 'bank_name' => $e->bank_name, 'bank_account_no' => $e->bank_account_no,
                    'status' => isset($held[$e->id]) ? 'HELD' : 'UNPAID', 'hold_reason' => $held[$e->id] ?? null, 'flags' => $r['flags'],
                ]);
                foreach ($r['lines'] as $l) {
                    $slip->lines()->create($l);
                }
                $sum['people']++;
                $sum['gross'] += $r['gross']; $sum['net'] += $r['net']; $sum['tax'] += $r['income_tax'];
                $sum['eobi_employee'] += $r['eobi_employee']; $sum['eobi_employer'] += $r['eobi_employer']; $sum['ss_employer'] += $r['ss_employer'];
                $sum['advances'] += $r['advance_recovery']; $sum['overtime_minutes'] += $r['overtime_minutes'];
                $sum['unclosed'] += collect($r['flags'])->contains(fn ($f) => str_starts_with($f, 'UNCLOSED')) ? 1 : 0;
                $sum['negative'] += in_array('NEGATIVE_NET', $r['flags'], true) ? 1 : 0;
            }
            $p->update(['status' => 'CALCULATED', 'calculated_at' => now(), 'summary' => $sum]);
        });

        return $sum;
    }

    /** Problems to fix before approval, and warnings to read. @return array{blockers:array<int,string>,warnings:array<int,string>} */
    public function checks(PayrollPeriod $p): array
    {
        $blockers = []; $warnings = [];
        $range = fn ($q) => $q->whereDate('work_date', '>=', $p->starts_on)->whereDate('work_date', '<=', $p->ends_on);
        if (! $p->payslips()->exists()) {
            $blockers[] = 'Nothing has been calculated yet.';
        }
        if ($n = $range(AttendanceRecord::where('exception_status', 'OPEN'))->count()) {
            $blockers[] = "$n attendance exception".($n === 1 ? '' : 's').' still open in this month. Resolve them under Attendance → Exceptions, then recalculate.';
        }
        if ($n = $range(AttendanceRecord::where('overtime_status', 'PENDING'))->count()) {
            $blockers[] = "$n overtime entr".($n === 1 ? 'y' : 'ies').' still waiting for approval. Decide them under Attendance → Overtime, then recalculate.';
        }
        if ($n = $p->payslips()->where('net', '<', 0)->count()) {
            $blockers[] = "$n payslip".($n === 1 ? ' has' : 's have').' a negative net pay.';
        }
        $s = $p->summary ?? [];
        if (! empty($s['no_salary'])) {
            $warnings[] = count($s['no_salary']).' employee'.(count($s['no_salary']) === 1 ? ' has' : 's have').' no salary on file and are not in this run: '.implode(', ', array_slice($s['no_salary'], 0, 6)).(count($s['no_salary']) > 6 ? '…' : '').'.';
        }
        if (! empty($s['unclosed'])) {
            $warnings[] = $s['unclosed'].' employee'.($s['unclosed'] === 1 ? ' has' : 's have').' working days with no attendance record (paid as '.strtolower(PayrollRules::get('missing_attendance')).'). Close the days under Attendance, then recalculate.';
        }
        if ($p->payslips()->where('payable_days', '<=', 0)->exists()) {
            $warnings[] = 'Some people have no payable days this month.';
        }
        if (PayrollAdjustment::whereDate('month', $p->month)->whereNull('payslip_id')->count() && $p->status !== 'DRAFT') {
            $warnings[] = 'There are one-off items for this month. Recalculate to include any added since the last calculation.';
        }

        return compact('blockers', 'warnings');
    }

    public function approve(PayrollPeriod $p, ?string &$message = null): bool
    {
        if ($p->status !== 'CALCULATED') {
            throw new RuntimeException('Only a calculated payroll can be approved.');
        }
        if ($b = $this->checks($p)['blockers']) {
            throw new RuntimeException(implode(' ', $b));
        }
        if (! app(ApprovalGate::class)->passes('hr.approve_payroll', 'PayrollPeriod', $p->id, 'Payroll '.$p->month->format('F Y'), $message)) {
            return false;
        }
        DB::transaction(function () use ($p) {
            foreach ($p->payslips()->with('lines')->get() as $slip) {
                foreach ($slip->lines->where('source', 'ADVANCE') as $l) {
                    $adv = EmployeeAdvance::find($l->ref_id);
                    if (! $adv) {
                        continue;
                    }
                    AdvanceLedgerEntry::create(['advance_id' => $adv->id, 'kind' => 'RECOVERY', 'amount' => -$l->amount, 'entry_date' => $p->ends_on, 'payslip_id' => $slip->id, 'note' => 'Payroll '.$p->month->format('M Y'), 'created_by_name' => Auth::user()?->name]);
                    if ($adv->balance() <= 0) {
                        $adv->update(['status' => 'CLEARED']);
                    }
                }
                foreach ($slip->lines->where('source', 'ADJUST') as $l) {
                    PayrollAdjustment::whereKey($l->ref_id)->update(['payslip_id' => $slip->id]);
                }
            }
            $p->update(['status' => 'APPROVED', 'approved_at' => now(), 'approved_by_name' => Auth::user()?->name]);
        });

        return true;
    }

    public function revert(PayrollPeriod $p): void
    {
        if ($p->status !== 'APPROVED') {
            throw new RuntimeException('Only an approved payroll can be sent back.');
        }
        if ($p->payslips()->where('status', 'PAID')->exists()) {
            throw new RuntimeException('Some payslips are already paid, so this payroll cannot be sent back.');
        }
        DB::transaction(function () use ($p) {
            $ids = $p->payslips()->pluck('id');
            $adv = AdvanceLedgerEntry::whereIn('payslip_id', $ids)->where('kind', 'RECOVERY')->pluck('advance_id')->unique();
            AdvanceLedgerEntry::whereIn('payslip_id', $ids)->where('kind', 'RECOVERY')->delete();
            foreach (EmployeeAdvance::whereIn('id', $adv)->where('status', 'CLEARED')->get() as $a) {
                if ($a->balance() > 0) {
                    $a->update(['status' => 'ACTIVE']);
                }
            }
            PayrollAdjustment::whereIn('payslip_id', $ids)->update(['payslip_id' => null]);
            $p->update(['status' => 'CALCULATED', 'approved_at' => null, 'approved_by_name' => null]);
        });
    }

    /** @param  array<int,string>|null  $ids  payslip ids; null means every unpaid one */
    public function markPaid(PayrollPeriod $p, ?array $ids, string $on, ?string $ref = null): int
    {
        if (! in_array($p->status, ['APPROVED', 'PAID'], true)) {
            throw new RuntimeException('Approve the payroll before recording payments.');
        }
        $q = $p->payslips()->where('status', 'UNPAID');
        if ($ids !== null) {
            $q->whereIn('id', $ids);
        }
        $n = $q->update(['status' => 'PAID', 'paid_on' => $on, 'payment_ref' => $ref]);
        if (! $p->payslips()->whereIn('status', ['UNPAID', 'HELD'])->exists()) {
            $p->update(['status' => 'PAID', 'paid_at' => now()]);
        }

        return $n;
    }

    public function hold(Payslip $s, string $reason): void
    {
        if ($s->status === 'PAID') {
            throw new RuntimeException('This payslip is already paid.');
        }
        $s->update(['status' => 'HELD', 'hold_reason' => $reason]);
    }

    public function release(Payslip $s): void
    {
        if ($s->status === 'HELD') {
            $s->update(['status' => 'UNPAID', 'hold_reason' => null]);
        }
    }
}
