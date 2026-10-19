<?php

namespace App\Domains\Hr\Services;

use App\Domains\Hr\Models\AdvanceLedgerEntry;
use App\Domains\Hr\Models\EmployeeAdvance;
use App\Domains\Hr\Models\EmployeeExit;
use App\Domains\Hr\Models\EmployeeSettlement;
use App\Domains\Hr\Models\EmployeeTask;
use App\Domains\Hr\Models\LeaveType;
use App\Domains\Hr\Models\PayrollAdjustment;
use App\Domains\Hr\Models\Payslip;
use App\Services\Approvals\ApprovalGate;
use App\Support\Formats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Full and final settlement: salary not yet paid up to the last day, leave paid out, gratuity, less tax and what the
 * person still owes. The figures are calculated; HR can add or remove manual lines before approving.
 */
class SettlementService
{
    public function __construct(private PayrollCalculator $calc, private SalaryService $salaries, private LeaveService $leave) {}

    public function open(EmployeeExit $x): EmployeeSettlement
    {
        $s = EmployeeSettlement::where('exit_id', $x->id)->first();
        if (! $s) {
            $s = EmployeeSettlement::create(['exit_id' => $x->id, 'employee_id' => $x->employee_id, 'last_working_day' => $x->last_working_day, 'status' => 'DRAFT']);
        }

        return $this->calculate($s);
    }

    public function calculate(EmployeeSettlement $s): EmployeeSettlement
    {
        if ($s->status !== 'DRAFT') {
            throw new RuntimeException('This settlement is already approved. Only a draft can be recalculated.');
        }
        $x = $s->exit;
        $e = $s->employee;
        $lwd = $x->last_working_day->copy()->startOfDay();
        $R = PayrollRules::all();
        $rd = fn (float $v) => PayrollRules::round($v);
        $lines = [];
        $add = function (string $kind, ?string $code, string $name, float $amt, ?string $note = null, ?string $ref = null) use (&$lines) {
            if (abs($amt) > 0.004) {
                $lines[] = ['kind' => $kind, 'code' => $code, 'name' => $name, 'amount' => $amt, 'auto' => true, 'note' => $note, 'ref_id' => $ref];
            }
        };

        // 1. Salary not yet paid through payroll, month by month up to the last day
        $paidThrough = Payslip::where('employee_id', $e->id)->whereHas('period', fn ($q) => $q->whereIn('status', ['APPROVED', 'PAID']))->with('period')->get()->map(fn ($p) => $p->period->ends_on)->max();
        $from = $paidThrough ? Carbon::parse($paidThrough)->addDay() : $e->joined_on->copy();
        if ($from->lt($e->joined_on)) {
            $from = $e->joined_on->copy();
        }
        for ($m = $from->copy()->startOfMonth(); $m->lte($lwd); $m->addMonth()->startOfMonth()) {
            $segStart = $m->copy()->max($from);
            $segEnd = $m->copy()->endOfMonth()->startOfDay()->min($lwd);
            if ($segStart->gt($segEnd)) {
                continue;
            }
            $r = $this->calc->compute($e, $segStart, $segEnd, ['advances' => false]);
            if (! $r) {
                continue;
            }
            $label = $segStart->format('M Y').' ('.$segStart->format('d').'–'.$segEnd->displayShortDate().')';
            $add('DUE', 'SALARY', 'Salary '.$label, (float) $r['total_earnings'], rtrim(rtrim(Formats::number($r['payable_days'], 2), '0'), '.').' payable days');
            foreach ($r['lines'] as $l) {
                if ($l['kind'] === 'DEDUCTION') {
                    $add('RECOVERY', $l['code'], $l['name'].' — '.$segStart->format('M Y'), (float) $l['amount']);
                }
            }
        }

        // 2. Leave paid out
        $sal = $this->salaries->at($e, $lwd);
        $basic = $sal ? (float) $sal->basic : 0.0;
        $daily = $basic / 30;
        foreach (LeaveType::whereIn('code', $R['encash_leave_codes'] ?? [])->get() as $t) {
            $bal = $this->leave->balance($e, $t);
            if ($bal > 0) {
                $add('DUE', 'LEAVE', 'Leave encashment — '.$t->name, $rd($bal * $daily), rtrim(rtrim(Formats::number($bal, 2), '0'), '.').' days at basic ÷ 30');
            }
        }

        // 3. Gratuity
        if ($R['gratuity_enabled'] && in_array($e->employment_type, $R['gratuity_types'] ?? [], true)) {
            $years = intdiv((int) $e->joined_on->diffInMonths($lwd->copy()->addDay()), 12);
            if ($years >= (int) $R['gratuity_min_years'] && $basic > 0) {
                $add('DUE', 'GRATUITY', 'Gratuity — '.$years.' completed year'.($years === 1 ? '' : 's'), $rd($years * (float) $R['gratuity_days_per_year'] * $daily), $R['gratuity_days_per_year'].' days of basic per year');
            }
        }

        // 4. What the person still owes
        foreach (EmployeeAdvance::where('employee_id', $e->id)->where('status', 'ACTIVE')->get() as $a) {
            $add('RECOVERY', $a->code, 'Outstanding '.strtolower($a->kind).' '.$a->code, $a->balance(), null, $a->id);
        }

        DB::transaction(function () use ($s, $lines) {
            $s->lines()->where('auto', true)->delete();
            $n = 0;
            foreach ($lines as $l) {
                $s->lines()->create($l + ['sort' => $n += 10]);
            }
            $this->total($s);
            $s->update(['calculated_at' => now(), 'last_working_day' => $s->exit->last_working_day]);
            $s->exit->update(['settlement_status' => 'CALCULATED']);
        });

        return $s->fresh('lines');
    }

    private function total(EmployeeSettlement $s): void
    {
        $due = (float) $s->lines()->where('kind', 'DUE')->sum('amount');
        $rec = (float) $s->lines()->where('kind', 'RECOVERY')->sum('amount');
        $s->update(['total_due' => $due, 'total_recovery' => $rec, 'net' => round($due - $rec, 2)]);
    }

    public function addLine(EmployeeSettlement $s, string $kind, string $name, float $amount, ?string $note): void
    {
        if ($s->status !== 'DRAFT') {
            throw new RuntimeException('Approved settlements cannot be changed.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Enter an amount above zero.');
        }
        $s->lines()->create(['kind' => $kind, 'code' => 'MANUAL', 'name' => $name, 'amount' => $amount, 'auto' => false, 'note' => $note, 'sort' => 900 + $s->lines()->count()]);
        $this->total($s);
    }

    public function removeLine(EmployeeSettlement $s, string $lineId): void
    {
        if ($s->status !== 'DRAFT') {
            throw new RuntimeException('Approved settlements cannot be changed.');
        }
        $s->lines()->where('id', $lineId)->where('auto', false)->delete();
        $this->total($s);
    }

    public function approve(EmployeeSettlement $s, ?string &$message = null): bool
    {
        if ($s->status !== 'DRAFT') {
            throw new RuntimeException('This settlement is already approved.');
        }
        if (! $s->lines()->exists()) {
            throw new RuntimeException('There is nothing in this settlement yet.');
        }
        $e = $s->employee;
        if (! app(ApprovalGate::class)->passes('hr.approve_settlement', 'EmployeeSettlement', $s->id, $e->code.' '.$e->full_name.' final settlement', $message)) {
            return false;
        }
        $s->update(['status' => 'APPROVED', 'approved_at' => now(), 'approved_by_name' => Auth::user()?->name]);
        $s->exit->update(['settlement_status' => 'APPROVED']);
        EmployeeTask::where('employee_id', $e->id)->where('kind', 'EXIT')->where('title', 'like', '%final settlement%')->whereNull('done_at')->update(['done_at' => now(), 'done_by_name' => Auth::user()?->name]);

        return true;
    }

    /** Record the payout. Outstanding advances are cleared on the ledger; one-off items for the months paid are marked used. */
    public function pay(EmployeeSettlement $s, string $on, string $method, ?string $ref): void
    {
        if ($s->status !== 'APPROVED') {
            throw new RuntimeException('Approve the settlement before recording the payment.');
        }
        DB::transaction(function () use ($s, $on, $method, $ref) {
            foreach ($s->lines()->where('kind', 'RECOVERY')->whereNotNull('ref_id')->get() as $l) {
                $a = EmployeeAdvance::find($l->ref_id);
                if ($a && $a->status === 'ACTIVE') {
                    AdvanceLedgerEntry::create(['advance_id' => $a->id, 'kind' => 'RECOVERY', 'amount' => -$l->amount, 'entry_date' => $on, 'settlement_id' => $s->id, 'note' => 'Final settlement', 'created_by_name' => Auth::user()?->name]);
                    if ($a->balance() <= 0) {
                        $a->update(['status' => 'CLEARED']);
                    }
                }
            }
            PayrollAdjustment::where('employee_id', $s->employee_id)->whereNull('payslip_id')->whereDate('month', '<=', $s->last_working_day)->update(['payslip_id' => $s->id]);
            $s->update(['status' => 'PAID', 'paid_on' => $on, 'payment_method' => $method, 'payment_ref' => $ref]);
            $s->exit->update(['settlement_status' => 'PAID']);
            if (! EmployeeAdvance::where('employee_id', $s->employee_id)->where('status', 'ACTIVE')->exists()) {
                EmployeeTask::where('employee_id', $s->employee_id)->where('kind', 'EXIT')->where('title', 'like', '%advances and loans%')->whereNull('done_at')->update(['done_at' => now(), 'done_by_name' => Auth::user()?->name]);
            }
        });
    }
}
