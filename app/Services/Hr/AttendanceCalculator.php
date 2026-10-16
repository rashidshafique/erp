<?php

namespace App\Services\Hr;

use App\Models\HrShift;
use Illuminate\Support\Carbon;

/** Turns a day plan and the punches into late, early, worked, overtime and the flags that need a person to look. Pure, no database. */
class AttendanceCalculator
{
    /**
     * @param  array{kind:string,shift:?HrShift,leave:mixed,holiday:?string}  $plan
     * @return array{status:string,late_minutes:int,early_out_minutes:int,worked_minutes:int,overtime_minutes:int,flags:array<int,string>,exception:bool}
     */
    public function compute(array $plan, $date, ?\Carbon\CarbonInterface $in, ?\Carbon\CarbonInterface $out): array
    {
        $cfg = config('hr.attendance');
        $r = ['status' => 'PRESENT', 'late_minutes' => 0, 'early_out_minutes' => 0, 'worked_minutes' => 0, 'overtime_minutes' => 0, 'flags' => [], 'exception' => false];
        $kind = $plan['kind'];
        $shift = $plan['shift'];
        $worked = ($in && $out && $out->gt($in)) ? (int) $in->diffInMinutes($out) : 0;

        if ($kind === 'LEAVE' && ! $in && ! $out) {
            $r['status'] = ($plan['leave']->half_day ?? false) ? 'HALF_DAY' : 'LEAVE';

            return $r;
        }
        if (in_array($kind, ['OFF', 'HOLIDAY'], true)) {
            if (! $in && ! $out) {
                $r['status'] = $kind;

                return $r;
            }
            $r['worked_minutes'] = $worked;
            $r['overtime_minutes'] = $worked;
            $r['flags'][] = 'WORKED_OFF';
            if ($worked > 0) {
                $r['flags'][] = 'OVERTIME';
            }
            if (! $in || ! $out) {
                $r['flags'][] = $in ? 'MISSING_OUT' : 'MISSING_IN';
                $r['exception'] = true;
            }

            return $r;
        }

        // A working day.
        if (! $in && ! $out) {
            $r['status'] = 'ABSENT';
            $r['flags'][] = 'ABSENT';
            $r['exception'] = true;

            return $r;
        }
        if (! $in || ! $out) {
            $r['flags'][] = $in ? 'MISSING_OUT' : 'MISSING_IN';
            $r['exception'] = true;
        }
        if ($shift && $in) {
            $start = $shift->startOn($date);
            if ($in->gt($start->copy()->addMinutes((int) $shift->grace_minutes))) {
                $r['late_minutes'] = (int) $start->diffInMinutes($in);
                $r['flags'][] = 'LATE';
            }
        }
        if ($shift && $out) {
            $end = $shift->endOn($date);
            if ($out->lt($end->copy()->subMinutes((int) $shift->grace_minutes))) {
                $r['early_out_minutes'] = (int) $out->diffInMinutes($end);
                $r['flags'][] = 'EARLY_OUT';
            }
            $over = $out->gt($end) ? (int) $end->diffInMinutes($out) : 0;
            if ($over >= (int) $cfg['overtime_min_minutes']) {
                $r['overtime_minutes'] = $over;
                $r['flags'][] = 'OVERTIME';
            }
        }
        if ($worked > 0 && $shift && $worked > 240) {
            $worked = max(0, $worked - (int) $shift->break_minutes);          // the break is taken out of a full day, not a short one
        }
        $r['worked_minutes'] = $worked;
        if ($shift && $worked > 0 && $in && $out && $worked < $shift->paidMinutes() * (float) $cfg['half_day_ratio']) {
            $r['status'] = 'HALF_DAY';
        }
        if ($kind === 'LEAVE') {                                   // someone on approved leave who came in anyway
            $r['flags'][] = 'WORKED_OFF';
        }

        return $r;
    }
}
