<?php

namespace App\Domains\Hr\Services;

use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\LeaveLedgerEntry;
use App\Domains\Hr\Models\LeaveType;
use Illuminate\Support\Carbon;

/** Adds entitlement to the leave ledger. Every entry has a reference, so running it twice never doubles anything. */
class LeaveAccrual
{
    private function people(Carbon $upTo)
    {
        return Employee::where('joined_on', '<=', $upTo->toDateString())->where(fn ($q) => $q->where('status', '!=', 'EXITED')->orWhere('exit_on', '>', $upTo->copy()->startOfMonth()->toDateString()))->get();
    }

    private function put(Employee $e, LeaveType $t, string $kind, float $days, string $ref, string $on, string $note): int
    {
        if ($days == 0.0) {
            return 0;
        }
        $made = LeaveLedgerEntry::firstOrCreate(['employee_id' => $e->id, 'leave_type_id' => $t->id, 'ref' => $ref], ['entry_date' => $on, 'kind' => $kind, 'days' => $days, 'note' => $note]);

        return $made->wasRecentlyCreated ? 1 : 0;
    }

    /** Monthly types: a twelfth of the year's days for each month worked. In the joining month, only if they joined by the 15th. */
    public function accrueMonth(Carbon $month): int
    {
        $m = $month->copy()->startOfMonth();
        $n = 0;
        foreach (LeaveType::where('active', true)->where('accrual', 'MONTHLY')->where('annual_days', '>', 0)->get() as $t) {
            foreach ($this->people($m->copy()->endOfMonth()) as $e) {
                if ($t->gender && $e->gender !== $t->gender) {
                    continue;
                }
                if ($e->joined_on->isSameMonth($m) && $e->joined_on->day > 15) {
                    continue;
                }
                if ($e->exit_on && $e->exit_on->lt($m)) {
                    continue;
                }
                $n += $this->put($e, $t, 'ACCRUAL', round($t->annual_days / 12, 2), 'ACC:'.$m->format('Y-m'), $m->copy()->endOfMonth()->toDateString(), 'Accrual '.$m->format('M Y'));
            }
        }

        return $n;
    }

    /** Up-front types: the whole year's days, pro rata for people who joined during the year. */
    public function grantYear(int $year): int
    {
        $n = 0;
        $start = Carbon::create($year, 1, 1);
        foreach (LeaveType::where('active', true)->where('accrual', 'UPFRONT')->where('annual_days', '>', 0)->get() as $t) {
            foreach ($this->people(Carbon::create($year, 12, 31)) as $e) {
                if (($t->gender && $e->gender !== $t->gender) || ($e->exit_on && $e->exit_on->year < $year)) {
                    continue;
                }
                $months = 12;
                if ($e->joined_on->year === $year) {
                    $months = 12 - $e->joined_on->month + ($e->joined_on->day <= 15 ? 1 : 0);
                }
                $days = round($t->annual_days * $months / 12 * 2) / 2;
                $n += $this->put($e, $t, 'GRANT', $days, 'GRANT:'.$year, $start->toDateString(), "Entitlement $year");
            }
        }

        return $n;
    }

    /** Everything due so far this year: the grant and every completed month. Safe to run as often as you like. */
    public function catchUp(?Carbon $today = null): array
    {
        $today ??= now();
        $y = $today->year;
        $granted = $this->grantYear($y);
        $accrued = 0;
        for ($m = 1; $m < $today->month; $m++) {
            $accrued += $this->accrueMonth(Carbon::create($y, $m, 1));
        }

        return ['granted' => $granted, 'accrued' => $accrued];
    }

    /** At year end: carry forward up to each type's cap and expire the rest. */
    public function closeYear(int $year): int
    {
        $n = 0;
        $on = Carbon::create($year, 12, 31)->toDateString();
        foreach (LeaveType::where('active', true)->where('accrual', '!=', 'NONE')->get() as $t) {
            foreach (Employee::where('joined_on', '<=', $on)->get() as $e) {
                $bal = round((float) LeaveLedgerEntry::where('employee_id', $e->id)->where('leave_type_id', $t->id)->whereDate('entry_date', '<=', $on)->sum('days'), 2);
                $drop = round($bal - min($bal, (float) $t->carry_forward_max), 2);
                if ($bal > 0 && $drop > 0) {
                    $n += $this->put($e, $t, 'EXPIRE', -$drop, 'EXP:'.$year, $on, "Not carried forward from $year");
                }
            }
        }

        return $n;
    }
}
