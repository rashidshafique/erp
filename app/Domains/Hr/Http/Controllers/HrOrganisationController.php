<?php

namespace App\Domains\Hr\Http\Controllers;

use App\Domains\Hr\Models\Employee;
use App\Domains\Hr\Models\EmployeeTask;
use App\Domains\Hr\Models\HrChecklistItem;
use App\Domains\Hr\Models\HrCostCentre;
use App\Domains\Hr\Models\HrDepartment;
use App\Domains\Hr\Models\HrDesignation;
use App\Domains\Hr\Models\HrGrade;
use App\Domains\Hr\Models\HrLocation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Departments, designations, grades, locations, cost centres and the joining / leaving checklists. */
class HrOrganisationController extends Controller
{
    public const KINDS = [
        'departments' => [HrDepartment::class, 'Department', 'department_id'],
        'designations' => [HrDesignation::class, 'Designation', 'designation_id'],
        'grades' => [HrGrade::class, 'Grade', 'grade_id'],
        'locations' => [HrLocation::class, 'Location', 'location_id'],
        'cost-centres' => [HrCostCentre::class, 'Cost centre', 'cost_centre_id'],
    ];

    public function index(): View
    {
        return view('hr::organisation', [
            'departments' => HrDepartment::with('parent', 'costCentre', 'head')->withCount('employees')->orderBy('name')->get(),
            'designations' => HrDesignation::with('grade')->orderBy('name')->get(),
            'grades' => HrGrade::orderBy('rank')->get(),
            'locations' => HrLocation::orderBy('name')->get(),
            'costCentres' => HrCostCentre::orderBy('code')->get(),
            'checklists' => HrChecklistItem::orderBy('kind')->orderBy('sort')->get()->groupBy('kind'),
            'employeeCounts' => collect(self::KINDS)->mapWithKeys(fn ($k) => [$k[2] => Employee::current()->whereNotNull($k[2])->groupBy($k[2])->selectRaw($k[2].' as k, count(*) as n')->pluck('n', 'k')]),
            'heads' => Employee::current()->orderBy('first_name')->get(),
        ]);
    }

    private function rules(string $kind, ?string $id = null): array
    {
        $code = ['nullable', 'string', 'max:30', Rule::unique($this->table($kind), 'code')->ignore($id)];
        $base = ['code' => $code, 'name' => ['required', 'string', 'max:120'], 'active' => ['nullable', 'boolean']];

        return match ($kind) {
            'departments' => $base + ['parent_id' => ['nullable', 'uuid', 'exists:hr_departments,id'], 'cost_centre_id' => ['nullable', 'uuid', 'exists:hr_cost_centres,id'], 'head_employee_id' => ['nullable', 'uuid', 'exists:employees,id']],
            'designations' => $base + ['grade_id' => ['nullable', 'uuid', 'exists:hr_grades,id']],
            'grades' => $base + ['rank' => ['required', 'integer', 'min:1', 'max:99'], 'min_salary' => ['nullable', 'numeric', 'min:0'], 'max_salary' => ['nullable', 'numeric', 'gte:min_salary']],
            'locations' => $base + ['kind' => ['required', Rule::in(array_keys(config('hr.location_kinds')))], 'city' => ['nullable', 'string', 'max:80'], 'address' => ['nullable', 'string', 'max:300']],
            'cost-centres' => $base,
        };
    }

    private function table(string $kind): string
    {
        return (new (self::KINDS[$kind][0]))->getTable();
    }

    private function spec(string $kind): array
    {
        abort_unless(isset(self::KINDS[$kind]), 404);

        return self::KINDS[$kind];
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        [$model, $label] = $this->spec($kind);
        $d = $request->validate($this->rules($kind));
        $d['active'] = true;
        if ($kind === 'departments' && ! empty($d['parent_id']) === false) {
            $d['parent_id'] = null;
        }
        $rec = $model::create(array_filter($d, fn ($v) => $v !== null && $v !== ''));

        return redirect()->route('hr.organisation', ['tab' => $kind])->with('success', "$label {$rec->code} added.");
    }

    public function update(Request $request, string $kind, string $id): RedirectResponse
    {
        [$model, $label] = $this->spec($kind);
        $rec = $model::findOrFail($id);
        $d = $request->validate($this->rules($kind, $id));
        if ($kind === 'departments' && ! empty($d['parent_id'])) {
            $this->assertNoDepartmentLoop($rec, $d['parent_id']);
        }
        $d['code'] = ($d['code'] ?? null) ?: $rec->code;
        $d['active'] = $request->boolean('active');
        foreach (['parent_id', 'cost_centre_id', 'head_employee_id', 'grade_id', 'city', 'address', 'min_salary', 'max_salary'] as $k) {
            if (array_key_exists($k, $d) && $d[$k] === '') {
                $d[$k] = null;
            }
        }
        $rec->update($d);

        return redirect()->route('hr.organisation', ['tab' => $kind])->with('success', "$label {$rec->code} saved.");
    }

    private function assertNoDepartmentLoop(HrDepartment $d, string $parentId): void
    {
        $p = HrDepartment::find($parentId);
        $guard = 0;
        while ($p && $guard++ < 20) {
            abort_if($p->id === $d->id, 422, 'A department cannot sit under itself.');
            $p = $p->parent;
        }
    }

    public function destroy(string $kind, string $id): RedirectResponse
    {
        [$model, $label, $col] = $this->spec($kind);
        $rec = $model::findOrFail($id);
        $used = Employee::where($col, $id)->exists() || ($kind === 'departments' && HrDepartment::where('parent_id', $id)->exists()) || ($kind === 'grades' && HrDesignation::where('grade_id', $id)->exists());
        if ($used) {
            return redirect()->route('hr.organisation', ['tab' => $kind])->with('error', "$label {$rec->code} is in use. Untick Active instead of deleting it.");
        }
        $rec->delete();

        return redirect()->route('hr.organisation', ['tab' => $kind])->with('success', "$label {$rec->code} deleted.");
    }

    public function checklistStore(Request $request): RedirectResponse
    {
        $d = $request->validate(['kind' => ['required', 'in:ONBOARDING,EXIT'], 'title' => ['required', 'string', 'max:200'], 'owner' => ['required', Rule::in(array_keys(EmployeeTask::OWNERS))], 'due_days' => ['required', 'integer', 'min:0', 'max:365']]);
        $d['sort'] = ((int) HrChecklistItem::where('kind', $d['kind'])->max('sort')) + 10;
        HrChecklistItem::create($d);

        return redirect()->route('hr.organisation', ['tab' => 'checklists'])->with('success', 'Checklist item added. It applies to people who join or leave from now on.');
    }

    public function checklistUpdate(Request $request, HrChecklistItem $item): RedirectResponse
    {
        $d = $request->validate(['title' => ['required', 'string', 'max:200'], 'owner' => ['required', Rule::in(array_keys(EmployeeTask::OWNERS))], 'due_days' => ['required', 'integer', 'min:0', 'max:365']]);
        $item->update($d + ['active' => $request->boolean('active')]);

        return redirect()->route('hr.organisation', ['tab' => 'checklists'])->with('success', 'Checklist item saved.');
    }

    public function checklistDestroy(HrChecklistItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('hr.organisation', ['tab' => 'checklists'])->with('success', 'Checklist item removed.');
    }
}
