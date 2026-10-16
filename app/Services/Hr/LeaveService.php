<?php

namespace App\Services\Hr;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveService
{
    public function __construct(private DayPlan $plans)
    {
    }

    public function balance(Employee $e, LeaveType $t): float
    {
        return round((float) LeaveLedgerEntry::where('employee_id', $e->id)->where('leave_type_id', $t->id)->sum('days'), 2);
    }

    /** @return array<int,array{type:LeaveType,balance:float}> */
    public function balances(Employee $e)
    {
        return LeaveType::where('active', true)->orderBy('name')->get()->filter(fn ($t) => $t->tracksBalance() && (! $t->gender || $t->gender === $e->gender))
            ->map(fn ($t) => ['type' => $t, 'balance' => $this->balance($e, $t)])->values();
    }

    /** Days a request would use: working days in the range, unless the type counts days off too. */
    public function daysFor(Employee $e, LeaveType $t, string $from, string $to, bool $half): float
    {
        $f = Carbon::parse($from)->startOfDay();
        $z = Carbon::parse($to)->startOfDay();
        if ($z->lt($f)) {
            throw new RuntimeException('The last day is before the first day.');
        }
        if ($half) {
            if (! $t->allow_half_day) {
                throw new RuntimeException($t->name.' cannot be taken as a half day.');
            }
            if (! $f->equalTo($z)) {
                throw new RuntimeException('A half day is a single day.');
            }
        }
        $off = $t->counts_days_off ? collect() : $this->plans->offDays($e, $f, $z)->flip();
        $n = 0.0;
        for ($d = $f->copy(); $d->lte($z); $d->addDay()) {
            if (! isset($off[$d->toDateString()])) {
                $n += 1;
            }
        }
        if ($n <= 0) {
            throw new RuntimeException('Every day in that range is already a day off or a holiday.');
        }

        return $half ? 0.5 : $n;
    }

    private function check(Employee $e, LeaveType $t, float $days, string $from, string $to, ?string $ignoreId = null): void
    {
        if (! $t->active) {
            throw new RuntimeException($t->name.' is not in use.');
        }
        if (! $e->isCurrent()) {
            throw new RuntimeException($e->full_name.' has left.');
        }
        if ($from < $e->joined_on->toDateString()) {
            throw new RuntimeException('That is before the joining date.');
        }
        if ($t->gender && $e->gender !== $t->gender) {
            throw new RuntimeException($t->name.' does not apply to this employee.');
        }
        if ($t->after_probation_only && in_array($e->status, ['ONBOARDING', 'PROBATION'], true)) {
            throw new RuntimeException($t->name.' can only be taken after probation.');
        }
        $clash = LeaveRequest::where('employee_id', $e->id)->whereIn('status', ['PENDING', 'APPROVED'])->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)->first();
        if ($clash) {
            throw new RuntimeException('That overlaps '.$clash->code.' ('.$clash->from_date->format('d M').' – '.$clash->to_date->format('d M').').');
        }
        if ($t->tracksBalance()) {
            $pending = (float) LeaveRequest::where('employee_id', $e->id)->where('leave_type_id', $t->id)->where('status', 'PENDING')->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->sum('days');
            $left = $this->balance($e, $t) - $pending;
            if ($days > $left + 0.001) {
                throw new RuntimeException('Not enough '.$t->name.': '.rtrim(rtrim(number_format($left, 2), '0'), '.').' day(s) available'.($pending ? " after {$pending} already asked for" : '').', '.rtrim(rtrim(number_format($days, 2), '0'), '.').' wanted.');
            }
        } elseif ($t->annual_days > 0 && $days > (float) $t->annual_days) {
            throw new RuntimeException($t->name.' is limited to '.rtrim(rtrim(number_format($t->annual_days, 2), '0'), '.').' days at a time.');
        }
    }

    public function request(Employee $e, LeaveType $t, string $from, string $to, bool $half, ?string $reason): LeaveRequest
    {
        $days = $this->daysFor($e, $t, $from, $to, $half);
        $this->check($e, $t, $days, $from, $to);

        return LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type_id' => $t->id, 'from_date' => $from, 'to_date' => $to, 'half_day' => $half, 'days' => $days, 'reason' => $reason,
            'status' => 'PENDING', 'requested_by_id' => Auth::id(), 'requested_by_name' => Auth::user()?->name,
        ]);
    }

    /** HR recording leave that has already been agreed (or charging an absence to leave). */
    public function fileAndApprove(Employee $e, LeaveType $t, string $from, string $to, bool $half, ?string $reason): LeaveRequest
    {
        $r = $this->request($e, $t, $from, $to, $half, $reason);
        $this->approve($r, 'Recorded by HR');

        return $r->refresh();
    }

    public function approve(LeaveRequest $r, ?string $note): void
    {
        DB::transaction(function () use ($r, $note) {
            $r = LeaveRequest::lockForUpdate()->findOrFail($r->id);
            if ($r->status !== 'PENDING') {
                throw new RuntimeException('This request is already '.strtolower($r->status).'.');
            }
            $e = $r->employee;
            $t = $r->type;
            // Re-check as if this request were not pending: the balance may have moved since it was made.
            $this->check($e, $t, (float) $r->days, $r->from_date->toDateString(), $r->to_date->toDateString(), $r->id);
            $r->update(['status' => 'APPROVED', 'decided_by_id' => Auth::id(), 'decided_by_name' => Auth::user()?->name, 'decided_at' => now(), 'decision_note' => $note]);
            if ($t->tracksBalance()) {
                LeaveLedgerEntry::create(['employee_id' => $e->id, 'leave_type_id' => $t->id, 'entry_date' => $r->from_date, 'kind' => 'TAKEN', 'days' => -$r->days, 'leave_request_id' => $r->id, 'note' => $r->code, 'created_by_name' => Auth::user()?->name]);
            }
            $this->markAttendance($r);
        });
    }

    public function reject(LeaveRequest $r, ?string $note): void
    {
        if ($r->status !== 'PENDING') {
            throw new RuntimeException('This request is already '.strtolower($r->status).'.');
        }
        $r->update(['status' => 'REJECTED', 'decided_by_id' => Auth::id(), 'decided_by_name' => Auth::user()?->name, 'decided_at' => now(), 'decision_note' => $note]);
    }

    public function cancel(LeaveRequest $r, ?string $note): void
    {
        DB::transaction(function () use ($r, $note) {
            $r = LeaveRequest::lockForUpdate()->findOrFail($r->id);
            if (! in_array($r->status, ['PENDING', 'APPROVED'], true)) {
                throw new RuntimeException('This request is already '.strtolower($r->status).'.');
            }
            $was = $r->status;
            $r->update(['status' => 'CANCELLED', 'decided_by_id' => Auth::id(), 'decided_by_name' => Auth::user()?->name, 'decided_at' => now(), 'decision_note' => $note]);
            if ($was === 'APPROVED') {
                if ($r->type->tracksBalance()) {
                    LeaveLedgerEntry::create(['employee_id' => $r->employee_id, 'leave_type_id' => $r->leave_type_id, 'entry_date' => now()->toDateString(), 'kind' => 'RESTORED', 'days' => $r->days, 'leave_request_id' => $r->id, 'note' => 'Cancelled '.$r->code, 'created_by_name' => Auth::user()?->name]);
                }
                AttendanceRecord::where('employee_id', $r->employee_id)->where('source', 'LEAVE')->whereDate('work_date', '>=', $r->from_date)->whereDate('work_date', '<=', $r->to_date)->delete();
            }
        });
    }

    /** Leave days show on the attendance sheet from the day they are approved. */
    private function markAttendance(LeaveRequest $r): void
    {
        $att = app(AttendanceService::class);
        $e = $r->employee;
        for ($d = $r->from_date->copy(); $d->lte($r->to_date); $d->addDay()) {
            $existing = AttendanceRecord::where('employee_id', $e->id)->whereDate('work_date', $d)->first();
            if ($existing && ($existing->check_in || $existing->check_out)) {
                $att->save($e, $d->toDateString(), $existing->check_in, $existing->check_out, null, $existing->source);    // came in anyway: keep the punches
            } else {
                $att->save($e, $d->toDateString(), null, null, $existing?->note, 'LEAVE');
            }
        }
    }

    public function adjust(Employee $e, LeaveType $t, float $days, string $note): void
    {
        if (abs($days) < 0.01) {
            throw new RuntimeException('Enter the days to add or take off.');
        }
        LeaveLedgerEntry::create(['employee_id' => $e->id, 'leave_type_id' => $t->id, 'entry_date' => now()->toDateString(), 'kind' => 'ADJUST', 'days' => $days, 'note' => $note, 'created_by_name' => Auth::user()?->name]);
    }
}
