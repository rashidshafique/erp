<?php

namespace App\Http\Controllers;

use App\Models\QualityDefect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DefectController extends Controller
{
    public const GROUPS = ['A' => 'Metallic projections', 'B' => 'Cavities', 'C' => 'Discontinuities', 'D' => 'Defective surface', 'E' => 'Incomplete casting', 'F' => 'Incorrect dimensions or shape', 'G' => 'Inclusions or structural anomalies'];
    public const CATEGORIES = ['SAND', 'GAS', 'SHRINKAGE', 'METALLURGICAL', 'POURING', 'DIMENSIONAL', 'CRACK', 'FETTLING', 'SURFACE', 'OTHER'];

    public function index(): View
    {
        return view('defects.index', ['defects' => QualityDefect::orderBy('category')->orderBy('name')->get(), 'groups' => self::GROUPS, 'categories' => self::CATEGORIES]);
    }

    public function store(Request $request): RedirectResponse
    {
        QualityDefect::create($this->data($request) + ['is_active' => true]);

        return back()->with('success', 'Defect added.');
    }

    public function update(Request $request, QualityDefect $defect): RedirectResponse
    {
        $defect->update($this->data($request, $defect) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', "{$defect->code} updated.");
    }

    private function data(Request $request, ?QualityDefect $defect = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('quality_defects', 'code')->ignore($defect?->id)],
            'name' => ['required', 'string', 'max:150'], 'category' => ['required', Rule::in(self::CATEGORIES)],
            'icf_group' => ['nullable', Rule::in(array_keys(self::GROUPS))], 'default_severity' => ['required', 'in:MINOR,MAJOR,CRITICAL'],
            'inspection_guidance' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
