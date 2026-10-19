<?php

namespace App\Http\Controllers;

use App\Models\Gauge;
use App\Models\Heat;
use App\Models\HeatTreatment;
use App\Services\Quality\CalibrationStatus;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HeatTreatmentController extends Controller
{
    public function store(Request $request, Heat $heat): RedirectResponse
    {
        $d = $request->validate([
            'treatment_type' => ['required', Rule::in(array_keys(HeatTreatment::TYPES))], 'treated_at' => ['nullable', 'date'],
            'material' => ['nullable', 'string', 'max:120'], 'amount_kg' => ['nullable', 'numeric', 'gt:0', 'max:10000'], 'metal_treated_kg' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'method' => ['nullable', Rule::in(array_keys(HeatTreatment::METHODS))], 'temperature_c' => ['nullable', 'numeric', 'between:900,1700'],
            'ladle_reference' => ['nullable', 'string', 'max:60'], 'recovery_pct' => ['nullable', 'numeric', 'between:0,100'],
            'treated_by' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
            'gauge_id' => ['nullable', 'uuid', Rule::exists('gauges', 'id')],
        ]);
        $at = isset($d['treated_at']) ? Carbon::parse($d['treated_at']) : now();
        if ($at->isFuture() && abs($at->diffInMinutes(now())) > 5) {
            return back()->withErrors(['treatment' => 'The treatment time cannot be in the future.'])->withInput();
        }
        $calibrationWarning = app(CalibrationStatus::class)->check(filled($d['gauge_id'] ?? null) ? Gauge::find($d['gauge_id']) : null, 'treatment');
        $d['treated_at'] = $at;
        if (! empty($d['amount_kg']) && ! empty($d['metal_treated_kg'])) {
            $d['addition_pct'] = round($d['amount_kg'] / $d['metal_treated_kg'] * 100, 3);
        }
        $heat->treatments()->create($d);

        return back()->with('success', HeatTreatment::TYPES[$d['treatment_type']].' recorded'.(isset($d['addition_pct']) ? " ({$d['addition_pct']} % addition)." : '.'))->with($calibrationWarning ? ['warning' => $calibrationWarning] : []);
    }

    public function destroy(Heat $heat, HeatTreatment $treatment): RedirectResponse
    {
        abort_unless($treatment->heat_id === $heat->id, 404);
        if ($heat->pours()->whereIn('status', ['IN_PROGRESS', 'COMPLETED'])->exists()) {
            return back()->withErrors(['treatment' => 'The heat has been poured; treatment records are part of the casting record and cannot be deleted.']);
        }
        $treatment->delete();

        return back()->with('success', 'Treatment record removed.');
    }
}
