<?php

namespace App\Http\Controllers;

use App\Models\Gauge;
use App\Models\Heat;
use App\Services\Quality\CalibrationStatus;
use App\Services\Quality\Measurements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class MaterialTestController extends Controller
{
    public function store(Request $request, Heat $heat, string $kind, Measurements $svc): RedirectResponse
    {
        abort_unless(in_array($kind, ['MECHANICAL', 'MICROSTRUCTURE'], true), 404);
        $d = $request->validate([
            'sample_reference' => ['nullable', 'string', 'max:80'], 'sample_type' => ['nullable', Rule::in(array_keys(Measurements::SAMPLE_TYPES))],
            'taken_at' => ['nullable', 'date'], 'tested_by' => ['nullable', 'string', 'max:100'], 'equipment_reference' => ['nullable', 'string', 'max:100'],
            'gauge_id' => ['nullable', 'uuid', Rule::exists('gauges', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'], 'values' => ['required', 'array'], 'values.*' => ['nullable', 'string', 'max:200'],
        ]);
        $gauge = filled($d['gauge_id'] ?? null) ? Gauge::find($d['gauge_id']) : null;
        $calibrationWarning = app(CalibrationStatus::class)->check($gauge, 'test_'.$kind);
        try {
            $set = $svc->record($kind, [
                'heat_id' => $heat->id, 'iron_grade_id' => $heat->iron_grade_id, 'sample_reference' => $d['sample_reference'] ?? null, 'sample_type' => $d['sample_type'] ?? null,
                'taken_at' => $d['taken_at'] ?? now(), 'tested_by' => $d['tested_by'] ?? null, 'equipment_reference' => ($d['equipment_reference'] ?? null) ?: $gauge?->code, 'gauge_id' => $gauge?->id, 'notes' => $d['notes'] ?? null,
            ], $d['values'], 'IRON_GRADE', $heat->iron_grade_id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['test_'.$kind => $e->getMessage()])->withInput();
        }

        return back()->with('success', ucfirst(strtolower($kind)).' result recorded: '.$set->result.($set->findings ? ' — '.implode('; ', $set->findings) : '.'))->with($calibrationWarning ? ['warning' => $calibrationWarning] : []);
    }
}
