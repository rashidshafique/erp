<?php

namespace App\Services\Hr;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AttendanceService
{
    public function __construct(private DayPlan $plans, private AttendanceCalculator $calc)
    {
    }

    /** Build a punch time on a work date from "HH:MM". A time earlier than the check-in belongs to the next morning (night shifts). */
    public function punch(string $date, ?string $time, ?\Carbon\CarbonInterface $after = null): ?Carbon
    {
        $time = trim((string) $time);
        if ($time === '') {
            return null;
        }
        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $time)) {
            throw new RuntimeException("\"$time\" is not a time (use HH:MM).");
        }
        $t = Carbon::parse("$date $time");
        if ($after && $t->lte($after)) {
            $t->addDay();
        }

        return $t;
    }

    /** Save one person's day from the punches given (null clears them). Returns the record. */
    public function save(Employee $e, $date, ?\Carbon\CarbonInterface $in, ?\Carbon\CarbonInterface $out, ?string $note = null, string $source = 'MANUAL', ?array $plan = null): AttendanceRecord
    {
        $d = Carbon::parse($date)->toDateString();
        if ($in && $out && $out->lte($in)) {
            throw new RuntimeException('Check-out must be after check-in.');
        }
        if ($d < $e->joined_on->toDateString()) {
            throw new RuntimeException($e->full_name.' had not joined on '.$d.'.');
        }
        $plan ??= $this->plans->one($e, $d);
        $c = $this->calc->compute($plan, $d, $in, $out);
        $rec = AttendanceRecord::firstOrNew(['employee_id' => $e->id, 'work_date' => $d]);
        $prevMinutes = (int) $rec->overtime_minutes;
        $prevOt = $rec->overtime_status ?: 'NONE';
        $prevStatus = $rec->status;

        $rec->fill([
            'shift_id' => $plan['shift']?->id, 'day_kind' => $plan['kind'], 'check_in' => $in, 'check_out' => $out, 'status' => $c['status'],
            'late_minutes' => $c['late_minutes'], 'early_out_minutes' => $c['early_out_minutes'], 'worked_minutes' => $c['worked_minutes'],
            'overtime_minutes' => $c['overtime_minutes'], 'flags' => $c['flags'], 'source' => $in || $out ? $source : ($plan['kind'] === 'LEAVE' ? 'LEAVE' : $source),
            'note' => $note ?? $rec->note, 'updated_by_id' => Auth::id(), 'updated_by_name' => Auth::user()?->name,
        ]);
        // Overtime: keep a decision when the amount did not change, otherwise it needs deciding again.
        if ($c['overtime_minutes'] <= 0) {
            $rec->overtime_status = 'NONE';
            $rec->overtime_approved_minutes = 0;
        } elseif ($prevOt !== 'NONE' && $prevMinutes === $c['overtime_minutes']) {
            $rec->overtime_status = $prevOt;
        } else {
            $auto = (bool) config('hr.attendance.overtime_auto_approve');
            $rec->overtime_status = $auto ? 'APPROVED' : 'PENDING';
            $rec->overtime_approved_minutes = $auto ? $c['overtime_minutes'] : 0;
        }
        // An exception stays settled only while the punches are the ones it was settled for.
        if ($c['exception']) {
            $changed = ! $rec->exists || $rec->isDirty('check_in') || $rec->isDirty('check_out');
            if ($changed || $rec->exception_status === 'NONE') {
                $rec->exception_status = 'OPEN';
                $rec->resolution = null;
            } elseif ($rec->exception_status === 'RESOLVED') {
                $rec->status = $prevStatus;
            }
        } else {
            $rec->exception_status = 'NONE';
            $rec->resolution = null;
        }
        $rec->save();

        return $rec;
    }

    /** Give every current employee a record for the day (absent, off, holiday or leave) where none exists yet. */
    public function closeDay($date): int
    {
        $d = Carbon::parse($date)->toDateString();
        $people = Employee::where('joined_on', '<=', $d)->where(fn ($q) => $q->where('status', '!=', 'EXITED')->orWhere('exit_on', '>=', $d))
            ->whereNotIn('id', AttendanceRecord::whereDate('work_date', $d)->select('employee_id'))->with('shift')->get();
        $plans = $this->plans->forDate($people, $d);
        foreach ($people as $e) {
            $this->save($e, $d, null, null, null, 'MANUAL', $plans[$e->id]);
        }

        return $people->count();
    }

    /**
     * How an open exception is settled.
     * PRESENT: excused, counts as present. ABSENT: confirmed absent. LEAVE:<type id>: charged to leave.
     */
    public function resolve(AttendanceRecord $rec, string $how, ?string $note): void
    {
        if ($rec->exception_status !== 'OPEN') {
            throw new RuntimeException('This one is not open.');
        }
        if ($how === 'PRESENT') {
            $rec->update(['status' => 'PRESENT', 'exception_status' => 'RESOLVED', 'resolution' => trim('Excused. '.$note)]);
        } elseif ($how === 'ABSENT') {
            $rec->update(['status' => 'ABSENT', 'exception_status' => 'RESOLVED', 'resolution' => trim('Confirmed absent. '.$note)]);
        } elseif (str_starts_with($how, 'LEAVE:')) {
            $type = LeaveType::findOrFail(substr($how, 6));
            $e = $rec->employee;
            app(LeaveService::class)->fileAndApprove($e, $type, $rec->work_date->toDateString(), $rec->work_date->toDateString(), false, 'Absence charged to leave. '.$note);
            $rec->refresh()->update(['exception_status' => 'RESOLVED', 'resolution' => trim('Charged to '.$type->name.'. '.$note)]);
        } else {
            throw new RuntimeException('Choose how to settle it.');
        }
    }

    public function decideOvertime(AttendanceRecord $rec, bool $approve, ?int $minutes): void
    {
        if ($rec->overtime_minutes <= 0) {
            throw new RuntimeException('There is no overtime on this day.');
        }
        if ($approve) {
            $m = $minutes === null ? $rec->overtime_minutes : max(0, min($minutes, $rec->overtime_minutes));
            $rec->update(['overtime_status' => $m > 0 ? 'APPROVED' : 'REJECTED', 'overtime_approved_minutes' => $m]);
        } else {
            $rec->update(['overtime_status' => 'REJECTED', 'overtime_approved_minutes' => 0]);
        }
    }

    /**
     * Punch log lines: employee code, date and time. First punch of a day is the check-in, last is the check-out.
     * A punch in the small hours after a night shift belongs to the shift that began the day before.
     *
     * @param  array<int,array{code:string,at:Carbon}>  $punches
     * @return array{saved:int,unknown:array<int,string>,problems:array<int,string>}
     */
    public function importPunches(array $punches): array
    {
        $byCode = [];
        foreach ($punches as $p) {
            $byCode[strtoupper($p['code'])][] = $p['at'];
        }
        $unknown = [];
        $problems = [];
        $saved = 0;
        foreach ($byCode as $code => $times) {
            $e = Employee::whereRaw('upper(code) = ?', [$code])->first();
            if (! $e) {
                $unknown[] = $code;

                continue;
            }
            usort($times, fn ($a, $b) => $a->timestamp <=> $b->timestamp);
            $days = [];
            foreach ($times as $t) {
                $key = $t->toDateString();
                $prev = $t->copy()->subDay()->toDateString();
                $prevRec = $days[$prev] ?? null;
                $plan = $this->plans->one($e, $prev);
                $night = $plan['shift']?->crossesMidnight();
                if ($night && $prevRec && count($prevRec) === 1 && $t->lte($plan['shift']->endOn($prev)->addHours(3))) {
                    $key = $prev;                                 // closes last night's shift
                }
                $days[$key][] = $t;
            }
            foreach ($days as $date => $ts) {
                try {
                    $in = $ts[0];
                    $out = count($ts) > 1 ? end($ts) : null;
                    if ($out && $in->diffInMinutes($out) < 1) {
                        $out = null;
                    }
                    $this->save($e, $date, $in, $out, null, 'IMPORT');
                    $saved++;
                } catch (RuntimeException $ex) {
                    $problems[] = "$code $date: ".$ex->getMessage();
                }
            }
        }

        return ['saved' => $saved, 'unknown' => $unknown, 'problems' => $problems];
    }

    /** Count of each day status for one person across a date range. */
    public function summary(Employee $e, $from, $to): array
    {
        $rows = AttendanceRecord::where('employee_id', $e->id)->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->get();
        $s = ['PRESENT' => 0, 'HALF_DAY' => 0, 'ABSENT' => 0, 'LEAVE' => 0, 'OFF' => 0, 'HOLIDAY' => 0, 'late' => 0, 'overtime' => 0, 'worked' => 0];
        foreach ($rows as $r) {
            $s[$r->status]++;
            $s['late'] += $r->late_minutes > 0 ? 1 : 0;
            $s['overtime'] += $r->overtime_status === 'APPROVED' ? $r->overtime_approved_minutes : 0;
            $s['worked'] += $r->worked_minutes;
        }

        return $s;
    }
}
