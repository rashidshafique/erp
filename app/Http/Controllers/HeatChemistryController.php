<?php

namespace App\Http\Controllers;

use App\Models\Gauge;
use App\Models\Heat;
use App\Models\HeatChemistryReading;
use App\Services\Production\Containment;
use App\Services\Quality\CalibrationStatus;
use App\Services\Quality\ChemistryJudge;
use App\Services\Quality\ChemistryRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HeatChemistryController extends Controller
{
    public function store(Request $request, Heat $heat, ChemistryRecorder $recorder): RedirectResponse
    {
        $pct = ['nullable', 'numeric', 'between:0,100'];
        $data = $request->validate([
            'sample_stage' => ['required', 'in:BASE,AFTER_TREATMENT,FINAL,PRE_POUR'],
            'test_method' => ['required', 'in:SPECTROMETER,WET_CHEMISTRY,CS_ANALYSER,OTHER'],
            'carbon_percent' => $pct, 'silicon_percent' => $pct, 'manganese_percent' => $pct, 'sulphur_percent' => $pct,
            'phosphorus_percent' => $pct, 'chromium_percent' => $pct, 'copper_percent' => $pct, 'molybdenum_percent' => $pct,
            'magnesium_percent' => $pct,
            'gauge_id' => ['nullable', 'uuid', Rule::exists('gauges', 'id')],
            'instrument_reference' => ['nullable', 'string', 'max:100'],
            'sample_reference' => ['nullable', 'string', 'max:100'],
            'tested_by' => ['nullable', 'string', 'max:100'],
            'tested_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (collect($data)->only(array_values(ChemistryJudge::COLUMNS))->filter(fn ($x) => $x !== null && $x !== '')->isEmpty()) {
            return back()->withErrors(['chemistry' => 'Enter at least one element result.'])->withInput();
        }

        $gauge = filled($data['gauge_id'] ?? null) ? Gauge::find($data['gauge_id']) : null;
        $calibrationWarning = app(CalibrationStatus::class)->check($gauge, 'gauge_id');
        if ($gauge && blank($data['instrument_reference'] ?? null)) {
            $data['instrument_reference'] = $gauge->code;
        }

        $result = $recorder->record($heat, $data);
        $reading = $result['reading'];
        $verdict = $result['verdict'];

        $msg = $verdict['ok'] === null ? 'Analysis saved (not judged).' : ($verdict['ok'] ? 'Analysis saved: within the grade limits.' : 'Analysis saved: OUT OF LIMITS. '.implode(' ', $verdict['findings']));

        $containment = app(Containment::class);
        $msg .= ' '.($containment->message($containment->apply($heat)) ?? '');

        return redirect()->route('heats.show', $heat)->with('success', trim($msg))->with('chemistry_reading', $reading->id)->with($calibrationWarning ? ['warning' => $calibrationWarning] : []);
    }

    public function verify(Heat $heat, HeatChemistryReading $reading): RedirectResponse
    {
        abort_unless($reading->heat_id === $heat->id, 404);
        $reading->update(['is_verified' => true]);

        return back()->with('success', "Analysis {$reading->reading_number} marked as verified.");
    }
}
