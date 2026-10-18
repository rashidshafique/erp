<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\HrDepartment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\DayPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

class AttendanceController extends Controller
{
    public function daily(Request $request, DayPlan $plans): View
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()))->startOfDay();
        $d = $date->toDateString();
        $people = Employee::where('joined_on', '<=', $d)->where(fn ($q) => $q->where('status', '!=', 'EXITED')->orWhere('exit_on', '>=', $d))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->department))
            ->with('department', 'shift')->orderBy('code')->get();
        $recs = AttendanceRecord::whereDate('work_date', $d)->whereIn('employee_id', $people->pluck('id'))->get()->keyBy('employee_id');
        $counts = AttendanceRecord::whereDate('work_date', $d)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');

        $prev = AttendanceRecord::whereDate('work_date', $date->copy()->subDay()->toDateString())->whereIn('employee_id', $people->pluck('id'))->get();

        return view('hr.attendance.daily', [
            'cards' => $this->cards($recs->values(), $prev),
            'date' => $date, 'people' => $people, 'recs' => $recs, 'plans' => $plans->forDate($people, $d), 'counts' => $counts,
            'departments' => HrDepartment::where('active', true)->orderBy('name')->get(), 'missing' => $people->count() - $recs->count(),
        ]);
    }

    /** The strip of numbers on top of the sheet, each against the day before. */
    private function cards($today, $before): array
    {
        $tally = fn ($c) => [
            'present' => $c->whereIn('status', ['PRESENT', 'HALF_DAY'])->count(), 'late' => $c->where('late_minutes', '>', 0)->count(),
            'absent' => $c->where('status', 'ABSENT')->count(), 'leave' => $c->where('status', 'LEAVE')->count(),
            'overtime' => $c->where('overtime_minutes', '>', 0)->count(), 'open' => $c->where('exception_status', 'OPEN')->count(),
        ];
        $a = $tally($today);
        $b = $tally($before);
        $labels = ['present' => 'Present', 'late' => 'Late clock-in', 'absent' => 'Absent', 'leave' => 'On leave', 'overtime' => 'Overtime', 'open' => 'To settle'];
        $good = ['present' => true, 'overtime' => true, 'late' => false, 'absent' => false, 'leave' => false, 'open' => false];

        return collect($labels)->map(fn ($l, $k) => ['key' => $k, 'label' => $l, 'n' => $a[$k], 'delta' => $b[$k] > 0 ? round(100 * ($a[$k] - $b[$k]) / $b[$k], 1) : null, 'good' => $good[$k]])->values()->all();
    }

    /** One person over a range: the side panel on the daily sheet. Returns a fragment, not a page. */
    public function person(Request $request, Employee $employee): View
    {
        $to = Carbon::parse($request->input('to', now()->toDateString()))->startOfDay();
        $days = in_array((int) $request->input('days', 7), [7, 14, 30], true) ? (int) $request->input('days', 7) : 7;
        $from = $to->copy()->subDays($days - 1);
        $employee->load('department', 'designation', 'shift');

        $recs = AttendanceRecord::with('shift')->where('employee_id', $employee->id)->whereDate('work_date', '>=', $from->toDateString())->whereDate('work_date', '<=', $to->toDateString())->get()->keyBy(fn ($r) => $r->work_date->toDateString());
        $before = AttendanceRecord::where('employee_id', $employee->id)->whereDate('work_date', '>=', $from->copy()->subDays($days)->toDateString())->whereDate('work_date', '<', $from->toDateString())->get();
        $stat = fn ($c) => [
            'off' => $c->whereIn('status', ['OFF', 'HOLIDAY'])->count(), 'late' => $c->where('late_minutes', '>', 0)->count(), 'early' => $c->where('early_out_minutes', '>', 0)->count(),
            'absent' => $c->where('status', 'ABSENT')->count(), 'leave' => $c->where('status', 'LEAVE')->count(), 'overtime' => $c->where('overtime_minutes', '>', 0)->count(),
        ];
        $now = $stat($recs->values());
        $was = $stat($before);
        $labels = ['off' => 'Day off', 'late' => 'Late clock-in', 'early' => 'Early clock-out', 'absent' => 'Absent', 'leave' => 'Leave', 'overtime' => 'Overtime'];
        $cards = collect($labels)->map(fn ($l, $k) => ['label' => $l, 'n' => $now[$k], 'delta' => $was[$k] > 0 ? round(100 * ($now[$k] - $was[$k]) / $was[$k], 1) : null, 'good' => in_array($k, ['overtime'], true)])->values()->all();

        // Timeline rows, newest first. Minutes are counted from midnight of the work date, so a night shift runs past 1440.
        $min = fn ($t, $d) => (int) Carbon::parse($d)->startOfDay()->diffInMinutes($t, false);
        $rows = [];
        $lo = 1440;
        $hi = 0;
        for ($d = $to->copy(); $d->gte($from); $d->subDay()) {
            $k = $d->toDateString();
            $r = $recs->get($k);
            $row = ['date' => $d->copy(), 'rec' => $r, 'bar' => null];
            if ($r && $r->check_in) {
                $a = $min($r->check_in, $k);
                $z = $r->check_out ? $min($r->check_out, $k) : null;
                $late = (int) $r->late_minutes;
                $ot = $z ? min((int) $r->overtime_minutes, $z - $a) : 0;
                $row['bar'] = ['in' => $a, 'out' => $z, 'late' => $late, 'ot' => $ot];
                $lo = min($lo, $a - $late);
                $hi = max($hi, $z ?? $a + 60);
            }
            $rows[] = $row;
        }
        $lo = $hi === 0 ? 8 * 60 : max(0, (int) floor(($lo - 45) / 60) * 60);
        $hi = $hi === 0 ? 18 * 60 : min(2160, (int) ceil(($hi + 45) / 60) * 60);
        if ($hi - $lo < 6 * 60) {
            $hi = $lo + 6 * 60;
        }

        return view('hr.attendance._person', [
            'e' => $employee, 'from' => $from, 'to' => $to, 'days' => $days, 'cards' => $cards, 'rows' => $rows, 'lo' => $lo, 'hi' => $hi,
            'overtime' => $recs->filter(fn ($r) => $r->overtime_minutes > 0)->sortByDesc('work_date')->values(),
            'leaves' => LeaveRequest::with('type')->where('employee_id', $employee->id)->whereDate('from_date', '<=', $to->toDateString())->whereDate('to_date', '>=', $from->toDateString())->orderByDesc('from_date')->get(),
        ]);
    }

    public function save(Request $request, AttendanceService $svc, DayPlan $plans): RedirectResponse
    {
        $d = $request->validate(['date' => ['required', 'date'], 'rows' => ['nullable', 'array'], 'rows.*.in' => ['nullable', 'string', 'max:8'], 'rows.*.out' => ['nullable', 'string', 'max:8'], 'rows.*.note' => ['nullable', 'string', 'max:300']]);
        $date = Carbon::parse($d['date'])->toDateString();
        $saved = 0;
        $problems = [];
        $people = Employee::whereIn('id', array_keys($d['rows'] ?? []))->with('shift')->get()->keyBy('id');
        $pl = $plans->forDate($people->values(), $date);
        foreach (($d['rows'] ?? []) as $id => $row) {
            $e = $people->get($id);
            if (! $e) {
                continue;
            }
            $existing = AttendanceRecord::where('employee_id', $id)->whereDate('work_date', $date)->first();
            $inT = trim((string) ($row['in'] ?? ''));
            $outT = trim((string) ($row['out'] ?? ''));
            $note = trim((string) ($row['note'] ?? '')) ?: null;
            if (! $existing && $inT === '' && $outT === '' && $note === null) {
                continue;
            }
            try {
                $in = $svc->punch($date, $inT);
                $out = $svc->punch($date, $outT, $in);
                $unchanged = $existing && (string) $existing->check_in?->displayTime() === (string) $in?->displayTime() && (string) $existing->check_out?->displayTime() === (string) $out?->displayTime() && ($existing->note ?? null) === $note;
                if ($unchanged) {
                    continue;
                }
                $svc->save($e, $date, $in, $out, $note, 'MANUAL', $pl[$id]);
                $saved++;
            } catch (RuntimeException $ex) {
                $problems[] = $e->code.': '.$ex->getMessage();
            }
        }
        $r = redirect()->route('hr.attendance', ['date' => $date, 'department' => $request->input('department')])->with('success', $saved ? "$saved day(s) saved." : 'Nothing to save.');

        return $problems ? $r->with('error', implode(' ', $problems)) : $r;
    }

    public function closeDay(Request $request, AttendanceService $svc): RedirectResponse
    {
        $d = $request->validate(['date' => ['required', 'date', 'before_or_equal:today']]);
        $n = $svc->closeDay($d['date']);

        return redirect()->route('hr.attendance', ['date' => $d['date']])->with('success', $n ? "$n more people were given a record (absent, day off, holiday or leave)." : 'Everyone already has a record for that day.');
    }

    public function exceptions(Request $request): View
    {
        $from = Carbon::parse($request->input('from', now()->subDays(30)->toDateString()))->toDateString();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->toDateString();
        $status = $request->input('status', 'OPEN');
        $rows = AttendanceRecord::with('employee.department', 'shift')->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)
            ->when($status === 'ALL', fn ($q) => $q->whereIn('exception_status', ['OPEN', 'RESOLVED']), fn ($q) => $q->where('exception_status', $status))
            ->orderByDesc('work_date')->orderBy('employee_id')->paginate(40)->withQueryString();

        return view('hr.attendance.exceptions', ['rows' => $rows, 'from' => $from, 'to' => $to, 'status' => $status, 'types' => LeaveType::where('active', true)->where('paid', true)->orderBy('name')->get()]);
    }

    public function resolve(Request $request, AttendanceRecord $record, AttendanceService $svc): RedirectResponse
    {
        $d = $request->validate(['how' => ['required', 'string', 'max:60'], 'note' => ['nullable', 'string', 'max:200']]);
        try {
            $svc->resolve($record, $d['how'], $d['note'] ?? null);
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage());
        }

        return back()->with('success', 'Settled.');
    }

    public function overtime(Request $request): View
    {
        $from = Carbon::parse($request->input('from', now()->subDays(30)->toDateString()))->toDateString();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->toDateString();
        $status = $request->input('status', 'PENDING');
        $rows = AttendanceRecord::with('employee.department')->where('overtime_minutes', '>', 0)->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)
            ->when($status !== 'ALL', fn ($q) => $q->where('overtime_status', $status))->orderByDesc('work_date')->orderBy('employee_id')->paginate(50)->withQueryString();

        return view('hr.attendance.overtime', compact('rows', 'from', 'to', 'status'));
    }

    public function overtimeDecide(Request $request, AttendanceService $svc): RedirectResponse
    {
        $d = $request->validate(['decision' => ['required', 'in:APPROVE,REJECT'], 'ids' => ['required', 'array', 'min:1'], 'ids.*' => ['uuid'], 'minutes' => ['nullable', 'array'], 'minutes.*' => ['nullable', 'integer', 'min:0', 'max:1440']]);
        $n = 0;
        foreach (AttendanceRecord::whereIn('id', $d['ids'])->get() as $rec) {
            try {
                $m = isset($d['minutes'][$rec->id]) && $d['minutes'][$rec->id] !== null ? (int) $d['minutes'][$rec->id] : null;
                $svc->decideOvertime($rec, $d['decision'] === 'APPROVE', $m);
                $n++;
            } catch (RuntimeException) {
            }
        }

        return back()->with('success', $n.' overtime entr'.($n === 1 ? 'y' : 'ies').($d['decision'] === 'APPROVE' ? ' approved.' : ' rejected.'));
    }

    public function importForm(): View
    {
        return view('hr.attendance.import');
    }

    public function import(Request $request, AttendanceService $svc): View|RedirectResponse
    {
        $request->validate(['file' => ['nullable', 'file', 'max:5120', 'extensions:csv,txt'], 'pasted' => ['nullable', 'string', 'max:500000']]);
        $text = $request->hasFile('file') ? (string) file_get_contents($request->file('file')->getRealPath()) : (string) $request->input('pasted');
        if (trim($text) === '') {
            return back()->with('error', 'Choose a file or paste the punch log.');
        }
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $punches = [];
        $bad = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cols = str_getcsv($line, str_contains($line, ';') ? ';' : (str_contains($line, "\t") ? "\t" : ','));
            $cols = array_map('trim', $cols);
            if (count($cols) >= 3) {
                $cols = [$cols[0], $cols[1].' '.$cols[2]];                  // code, date, time
            }
            if (count($cols) < 2) {
                $bad[] = 'Line '.($i + 1).': need a code and a date/time.';

                continue;
            }
            try {
                $punches[] = ['code' => $cols[0], 'at' => Carbon::parse($cols[1])];
            } catch (\Throwable) {
                if ($i > 0) {
                    $bad[] = 'Line '.($i + 1).': "'.$cols[1].'" is not a date and time.';
                }                                                          // the first line may be a heading
            }
        }
        if (! $punches) {
            return back()->with('error', 'No punches could be read. '.implode(' ', array_slice($bad, 0, 3)));
        }
        $res = $svc->importPunches($punches);

        return view('hr.attendance.import', ['result' => $res + ['punches' => count($punches), 'bad' => $bad]]);
    }
}
