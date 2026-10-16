<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\HeatChemistryReading;
use App\Services\Quality\ChemistryJudge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HeatChemistryController extends Controller
{
    public function store(Request $request, Heat $heat, ChemistryJudge $judge): RedirectResponse
    {
        $pct = ['nullable', 'numeric', 'between:0,100'];
        $data = $request->validate([
            'sample_stage' => ['required', 'in:BASE,AFTER_TREATMENT,FINAL,PRE_POUR'],
            'test_method' => ['required', 'in:SPECTROMETER,WET_CHEMISTRY,CS_ANALYSER,OTHER'],
            'carbon_percent' => $pct, 'silicon_percent' => $pct, 'manganese_percent' => $pct, 'sulphur_percent' => $pct,
            'phosphorus_percent' => $pct, 'chromium_percent' => $pct, 'copper_percent' => $pct, 'molybdenum_percent' => $pct,
            'magnesium_percent' => $pct,
            'instrument_reference' => ['nullable', 'string', 'max:100'],
            'sample_reference' => ['nullable', 'string', 'max:100'],
            'tested_by' => ['nullable', 'string', 'max:100'],
            'tested_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (collect($data)->only(array_values(ChemistryJudge::COLUMNS))->filter(fn ($x) => $x !== null && $x !== '')->isEmpty()) {
            return back()->withErrors(['chemistry' => 'Enter at least one element result.'])->withInput();
        }

        $heat->loadMissing('ironGrade');
        $ce = $judge->carbonEquivalent($data);
        $verdict = $judge->judge($heat->ironGrade, $data, $ce);

        $reading = DB::transaction(function () use ($heat, $data, $ce, $verdict) {
            Heat::whereKey($heat->id)->lockForUpdate()->first();
            $next = (int) HeatChemistryReading::where('heat_id', $heat->id)->max('reading_number') + 1;

            return HeatChemistryReading::create($data + [
                'heat_id' => $heat->id,
                'reading_number' => $next,
                'carbon_equivalent' => $ce,
                'is_accepted' => $verdict['ok'],
                'tested_at' => $data['tested_at'] ?? now(),
            ]);
        });

        $msg = $verdict['ok'] === null ? 'Analysis saved (not judged).' : ($verdict['ok'] ? 'Analysis saved: within the grade limits.' : 'Analysis saved: OUT OF LIMITS. '.implode(' ', $verdict['findings']));

        return redirect()->route('heats.show', $heat)->with('success', $msg)->with('chemistry_reading', $reading->id);
    }

    public function verify(Heat $heat, HeatChemistryReading $reading): RedirectResponse
    {
        abort_unless($reading->heat_id === $heat->id, 404);
        $reading->update(['is_verified' => true]);

        return back()->with('success', "Analysis {$reading->reading_number} marked as verified.");
    }
}
