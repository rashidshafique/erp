<?php

namespace App\Http\Controllers;

use App\Models\Furnace;
use App\Models\FurnaceGradeCapability;
use App\Models\IronGrade;
use App\Models\ThermalWindow;
use App\Services\FurnaceEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IronGradeController extends Controller
{
    public function index(): View
    {
        return view('iron-grades.index', [
            'grades' => IronGrade::withCount('aliases')->orderBy('family')->orderBy('code')->get(),
            'windows' => ThermalWindow::pluck('iron_grade_id')->unique()->all(),
        ]);
    }

    public function show(IronGrade $ironGrade, FurnaceEligibility $eligibility): View
    {
        $ironGrade->load('aliases');
        $furnaces = Furnace::where('status', 'ACTIVE')->orderBy('code')->get();

        return view('iron-grades.show', [
            'grade' => $ironGrade,
            'window' => ThermalWindow::firstOrNew(['iron_grade_id' => $ironGrade->id, 'cup_type' => 'STABLE']),
            'furnaces' => $furnaces,
            'can' => $furnaces->mapWithKeys(fn ($f) => [$f->id => $eligibility->canMelt($f, $ironGrade)]),
            'overrides' => FurnaceGradeCapability::where('iron_grade_id', $ironGrade->id)->pluck('allowed', 'furnace_id'),
        ]);
    }

    public function update(Request $request, IronGrade $ironGrade): RedirectResponse
    {
        $rules = ['name' => ['required', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000']];
        foreach (array_keys(IronGrade::ELEMENTS) as $e) {
            $rules["{$e}_min"] = ['nullable', 'numeric', 'min:0', 'max:100'];
            $rules["{$e}_max"] = array_merge(['nullable', 'numeric', 'min:0', 'max:100'], $request->filled("{$e}_min") ? ["gte:{$e}_min"] : []);
        }
        foreach (['ce_min', 'ce_max'] as $k) {
            $rules[$k] = ['nullable', 'numeric', 'min:0', 'max:10'];
        }
        if ($request->filled('ce_min')) {
            $rules['ce_max'][] = 'gte:ce_min';
        }
        foreach (['tap_temp_min_c', 'tap_temp_max_c', 'pour_temp_min_c', 'pour_temp_max_c'] as $k) {
            $rules[$k] = ['nullable', 'integer', 'min:1000', 'max:1700'];
        }
        if ($request->filled('tap_temp_min_c')) {
            $rules['tap_temp_max_c'][] = 'gte:tap_temp_min_c';
        }
        if ($request->filled('pour_temp_min_c')) {
            $rules['pour_temp_max_c'][] = 'gte:pour_temp_min_c';
        }

        $ironGrade->update($request->validate($rules));

        return back()->with('success', 'Grade limits saved.');
    }

    public function updateWindow(Request $request, IronGrade $ironGrade): RedirectResponse
    {
        $pair = fn (string $min, array $base) => $request->filled($min) ? array_merge($base, ["gte:{$min}"]) : $base;
        $data = $request->validate([
            'tl_min_c' => ['nullable', 'numeric', 'between:1000,1500'],
            'tl_max_c' => $pair('tl_min_c', ['nullable', 'numeric', 'between:1000,1500']),
            'ce_min' => ['nullable', 'numeric', 'between:2,6'],
            'ce_max' => $pair('ce_min', ['nullable', 'numeric', 'between:2,6']),
            'te_min_floor_c' => ['nullable', 'numeric', 'between:1000,1300'],
            'recal_min_c' => ['nullable', 'numeric', 'between:0,60'],
            'recal_max_c' => $pair('recal_min_c', ['nullable', 'numeric', 'between:0,60']),
            'solidus_min_c' => ['nullable', 'numeric', 'between:900,1300'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        ThermalWindow::updateOrCreate(['iron_grade_id' => $ironGrade->id, 'cup_type' => 'STABLE'], $data);

        return back()->with('success', 'Thermal acceptance window saved.');
    }

    public function setCapability(Request $request, IronGrade $ironGrade): RedirectResponse
    {
        $data = $request->validate([
            'furnace_id' => ['required', 'uuid', 'exists:furnaces,id'],
            'mode' => ['required', 'in:ALLOW,DENY,DEFAULT'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['mode'] === 'DEFAULT') {
            FurnaceGradeCapability::where('furnace_id', $data['furnace_id'])->where('iron_grade_id', $ironGrade->id)->delete();
        } else {
            FurnaceGradeCapability::updateOrCreate(
                ['furnace_id' => $data['furnace_id'], 'iron_grade_id' => $ironGrade->id],
                ['allowed' => $data['mode'] === 'ALLOW', 'notes' => $data['notes'] ?? null]
            );
        }

        return back()->with('success', 'Furnace capability saved.');
    }
}
