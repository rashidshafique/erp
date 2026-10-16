<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Services\Hr\Lifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The organisation as a chart: who reports to whom, with a dialog to add a person under anyone. */
class OrgChartController extends Controller
{
    public function index(): View
    {
        $all = Employee::current()->with('designation', 'department')->orderBy('first_name')->get();
        $ids = $all->pluck('id')->flip();
        $byManager = $all->groupBy(fn ($e) => $e->manager_id && isset($ids[$e->manager_id]) ? $e->manager_id : 'ROOT');

        // Level = steps below the top. A loop in the data cannot hang this: each person is placed once.
        $level = [];
        $queue = [['ROOT', 1]];
        while ($queue) {
            [$key, $lv] = array_shift($queue);
            foreach ($byManager->get($key, collect()) as $e) {
                if (isset($level[$e->id])) {
                    continue;
                }
                $level[$e->id] = $lv;
                $queue[] = [$e->id, $lv + 1];
            }
        }
        $levels = $all->filter(fn ($e) => isset($level[$e->id]))->groupBy(fn ($e) => $level[$e->id])->sortKeys();

        return view('hr.orgchart', [
            'byManager' => $byManager, 'roots' => $byManager->get('ROOT', collect()), 'levels' => $levels, 'level' => $level, 'total' => $all->count(),
            'designations' => HrDesignation::where('active', true)->orderBy('name')->get(), 'departments' => HrDepartment::where('active', true)->orderBy('name')->get(),
            'people' => $all,
        ]);
    }

    public function store(Request $request, Lifecycle $life): RedirectResponse
    {
        $d = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['nullable', 'string', 'max:80'],
            'designation_id' => ['nullable', 'uuid', 'exists:hr_designations,id'], 'department_id' => ['nullable', 'uuid', 'exists:hr_departments,id'],
            'manager_id' => ['nullable', 'uuid', Rule::exists('employees', 'id')->where(fn ($q) => $q->where('status', '!=', 'EXITED')->whereNull('deleted_at'))],
            'work_email' => ['nullable', 'email', 'max:160'], 'phone' => ['nullable', 'string', 'max:40'],
            'employment_type' => ['required', Rule::in(array_keys(config('hr.employment_types')))],
            'joined_on' => ['required', 'date', 'before_or_equal:+1 year'],
        ]);
        foreach ($d as $k => $v) {
            if ($v === '') {
                $d[$k] = null;
            }
        }
        $d['status'] = 'ONBOARDING';
        if ($d['employment_type'] === 'PERMANENT') {
            $d['probation_ends_on'] = Carbon::parse($d['joined_on'])->addMonths((int) config('hr.probation_months'))->toDateString();
        }
        $e = Employee::create($d);
        $life->joined($e->load('designation'));

        return redirect()->route('hr.orgchart', ['focus' => $e->id])->with('success', "{$e->code} {$e->full_name} added".($e->manager_id ? ' under '.Employee::find($e->manager_id)?->full_name : ' at the top level').'. Their joining checklist is ready.');
    }
}
