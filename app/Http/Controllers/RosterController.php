<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\HrDepartment;
use App\Models\HrHoliday;
use App\Models\HrRosterEntry;
use App\Models\HrShift;
use App\Services\Hr\DayPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Who works which shift on which day, the shifts themselves, and the holiday calendar. */
class RosterController extends Controller
{
    public function index(Request $request, DayPlan $plans): View
    {
        $week = Carbon::parse($request->input('week', now()->toDateString()))->startOfWeek(Carbon::MONDAY);
        $days = collect(range(0, 6))->map(fn ($i) => $week->copy()->addDays($i));
        $people = Employee::current()->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->department))->with('department', 'shift')->orderBy('code')->get();
        $entries = HrRosterEntry::whereDate('work_date', '>=', $days->first())->whereDate('work_date', '<=', $days->last())->whereIn('employee_id', $people->pluck('id'))->get()
            ->groupBy('employee_id')->map(fn ($g) => $g->keyBy(fn ($r) => $r->work_date->toDateString()));
        $defaults = [];
        foreach ($days as $d) {
            $defaults[$d->toDateString()] = $plans->forDate($people, $d);
        }
        $year = (int) $request->input('year', now()->year);

        return view('hr.roster', [
            'week' => $week, 'days' => $days, 'people' => $people, 'entries' => $entries, 'defaults' => $defaults,
            'shifts' => HrShift::orderBy('starts_at')->get(), 'departments' => HrDepartment::where('active', true)->orderBy('name')->get(),
            'year' => $year, 'holidays' => HrHoliday::whereYear('holiday_on', $year)->orderBy('holiday_on')->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $d = $request->validate(['week' => ['required', 'date'], 'cells' => ['nullable', 'array'], 'cells.*' => ['array'], 'cells.*.*' => ['nullable', 'string', 'max:40']]);
        $shiftIds = HrShift::pluck('id')->flip();
        $n = 0;
        foreach (($d['cells'] ?? []) as $empId => $row) {
            if (! Employee::whereKey($empId)->exists()) {
                continue;
            }
            foreach ($row as $date => $v) {
                $date = Carbon::parse($date)->toDateString();
                if ($v === '' || $v === null) {
                    $n += HrRosterEntry::where('employee_id', $empId)->whereDate('work_date', $date)->delete();
                } elseif ($v === 'OFF' || isset($shiftIds[$v])) {
                    $row2 = HrRosterEntry::updateOrCreate(['employee_id' => $empId, 'work_date' => $date], ['shift_id' => $v === 'OFF' ? null : $v]);
                    $n += $row2->wasRecentlyCreated || $row2->wasChanged() ? 1 : 0;
                }
            }
        }

        return redirect()->route('hr.roster', ['week' => $d['week'], 'department' => $request->input('department')])->with('success', $n ? 'Roster saved.' : 'No changes.');
    }

    private function shiftRules(?string $id = null): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'starts_at' => ['required', 'date_format:H:i'], 'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'], 'break_minutes' => ['required', 'integer', 'min:0', 'max:240'], 'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'], 'code' => ['nullable', 'string', 'max:30', Rule::unique('hr_shifts', 'code')->ignore($id)]];
    }

    public function shiftStore(Request $request): RedirectResponse
    {
        $d = $request->validate($this->shiftRules());
        $d['active'] = true;
        HrShift::create(array_filter($d, fn ($v) => $v !== null && $v !== ''));

        return redirect()->route('hr.roster', ['tab' => 'shifts'])->with('success', 'Shift added.');
    }

    public function shiftUpdate(Request $request, HrShift $shift): RedirectResponse
    {
        $d = $request->validate($this->shiftRules($shift->id));
        $d['code'] = ($d['code'] ?? null) ?: $shift->code;
        $d['active'] = $request->boolean('active');
        $shift->update($d);

        return redirect()->route('hr.roster', ['tab' => 'shifts'])->with('success', 'Shift saved. Days already recorded keep the times they were worked against.');
    }

    public function holidayStore(Request $request): RedirectResponse
    {
        $d = $request->validate(['holiday_on' => ['required', 'date'], 'name' => ['required', 'string', 'max:120'], 'kind' => ['required', 'in:PUBLIC,COMPANY,OPTIONAL']]);
        HrHoliday::firstOrCreate(['holiday_on' => $d['holiday_on'], 'name' => $d['name']], ['kind' => $d['kind']]);

        return redirect()->route('hr.roster', ['tab' => 'holidays', 'year' => Carbon::parse($d['holiday_on'])->year])->with('success', 'Holiday added.');
    }

    public function holidayDestroy(HrHoliday $holiday): RedirectResponse
    {
        $y = $holiday->holiday_on->year;
        $holiday->delete();

        return redirect()->route('hr.roster', ['tab' => 'holidays', 'year' => $y])->with('success', 'Holiday removed.');
    }

    /** The holidays that fall on the same date every year. Moon-sighting dates (Eid, Ashura) are added by hand. */
    public function holidayFixed(Request $request): RedirectResponse
    {
        $y = (int) $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100']])['year'];
        $n = 0;
        foreach (config('hr.fixed_holidays') as [$md, $name]) {
            $n += HrHoliday::firstOrCreate(['holiday_on' => "$y-$md", 'name' => $name], ['kind' => 'PUBLIC'])->wasRecentlyCreated ? 1 : 0;
        }

        return redirect()->route('hr.roster', ['tab' => 'holidays', 'year' => $y])->with('success', "$n fixed-date holiday(s) added for $y. Add Eid and other moon-dependent days yourself.");
    }
}
