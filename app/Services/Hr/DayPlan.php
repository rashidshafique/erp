<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\HrHoliday;
use App\Models\HrRosterEntry;
use App\Models\HrShift;
use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a day is meant to be for a person: work (on which shift), a weekly off, a holiday or approved leave.
 * Order of precedence: approved leave, then the roster, then holidays, then the weekly off, then the default shift.
 */
class DayPlan
{
    /** @var array<string,HrShift> */
    private array $shifts;

    public function __construct()
    {
        $this->shifts = HrShift::all()->keyBy('id')->all();
    }

    /**
     * @param  iterable<Employee>  $employees
     * @return array<string,array{kind:string,shift:?HrShift,leave:?LeaveRequest,holiday:?string}>  keyed by employee id
     */
    public function forDate(iterable $employees, $date): array
    {
        $d = Carbon::parse($date)->startOfDay();
        $ds = $d->toDateString();
        $employees = collect($employees);
        $ids = $employees->pluck('id')->all();
        $holiday = HrHoliday::whereDate('holiday_on', $ds)->orderBy('name')->get()->pluck('name')->implode(', ') ?: null;
        $roster = HrRosterEntry::whereDate('work_date', $ds)->whereIn('employee_id', $ids)->get()->keyBy('employee_id');
        $leave = LeaveRequest::where('status', 'APPROVED')->whereDate('from_date', '<=', $ds)->whereDate('to_date', '>=', $ds)->whereIn('employee_id', $ids)->with('type')->get()->keyBy('employee_id');

        $out = [];
        foreach ($employees as $e) {
            $shift = $e->shift_id ? ($this->shifts[$e->shift_id] ?? null) : null;
            $plan = ['kind' => 'WORK', 'shift' => $shift, 'leave' => null, 'holiday' => null];
            if ($l = $leave->get($e->id)) {
                $plan['kind'] = 'LEAVE';
                $plan['leave'] = $l;
            } elseif ($r = $roster->get($e->id)) {
                if ($r->shift_id) {
                    $plan['shift'] = $this->shifts[$r->shift_id] ?? $shift;      // rostered to work, even on a holiday or weekly off
                } else {
                    $plan['kind'] = 'OFF';
                }
            } elseif ($holiday) {
                $plan['kind'] = 'HOLIDAY';
                $plan['holiday'] = $holiday;
            } elseif (in_array((string) $d->dayOfWeek, array_filter(explode(',', (string) $e->weekly_off), 'strlen'), true)) {
                $plan['kind'] = 'OFF';
            }
            $out[$e->id] = $plan;
        }

        return $out;
    }

    public function one(Employee $e, $date): array
    {
        return $this->forDate([$e], $date)[$e->id];
    }

    /** Dates in a range that are days off or holidays for one person. Used to count leave days. */
    public function offDays(Employee $e, $from, $to): Collection
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $holidays = HrHoliday::whereDate('holiday_on', '>=', $from)->whereDate('holiday_on', '<=', $to)->get()->map(fn ($h) => $h->holiday_on->toDateString())->flip();
        $roster = HrRosterEntry::where('employee_id', $e->id)->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->get()->keyBy(fn ($r) => $r->work_date->toDateString());
        $weekly = array_filter(explode(',', (string) $e->weekly_off), 'strlen');
        $off = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $k = $d->toDateString();
            if ($r = $roster->get($k)) {
                $isOff = $r->shift_id === null;
            } else {
                $isOff = isset($holidays[$k]) || in_array((string) $d->dayOfWeek, $weekly, true);
            }
            if ($isOff) {
                $off->push($k);
            }
        }

        return $off;
    }
}
