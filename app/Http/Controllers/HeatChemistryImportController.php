<?php

namespace App\Http\Controllers;

use App\Models\Gauge;
use App\Models\Heat;
use App\Services\Production\Containment;
use App\Services\Quality\CalibrationStatus;
use App\Services\Quality\ChemistryImport;
use App\Services\Quality\ChemistryRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HeatChemistryImportController extends Controller
{
    /** Records every sample row of a pasted or uploaded spectrometer export as a chemistry reading of the heat. */
    public function store(Request $request, Heat $heat, ChemistryImport $import, ChemistryRecorder $recorder): RedirectResponse
    {
        $data = $request->validate([
            'results' => ['nullable', 'string', 'max:200000'],
            'results_file' => ['nullable', 'file', 'max:2048', 'extensions:csv,txt,tsv'],
            'test_method' => ['required', 'in:SPECTROMETER,WET_CHEMISTRY,CS_ANALYSER,OTHER'],
            'sample_stage' => ['required', 'in:BASE,AFTER_TREATMENT,FINAL,PRE_POUR'],
            'gauge_id' => ['nullable', 'uuid', Rule::exists('gauges', 'id')],
            'instrument_reference' => ['nullable', 'string', 'max:100'],
        ]);
        $gauge = filled($data['gauge_id'] ?? null) ? Gauge::find($data['gauge_id']) : null;
        $calibrationWarning = app(CalibrationStatus::class)->check($gauge, 'gauge_id');
        $text = $request->hasFile('results_file') ? file_get_contents($request->file('results_file')->getRealPath()) : (string) ($data['results'] ?? '');
        $parsed = $import->parse($text);
        if ($parsed['rows'] === []) {
            return back()->withErrors(['chemistry' => implode(' ', $parsed['errors'] ?: ['No sample rows found.'])])->withInput();
        }

        $rows = collect($parsed['rows'])->sortBy(fn ($r) => isset($r['tested_at']) ? $r['tested_at']->getTimestamp() : 0);
        $failed = 0;
        foreach ($rows as $row) {
            $result = $recorder->record($heat, $row + ['test_method' => $data['test_method'], 'sample_stage' => $data['sample_stage'], 'instrument_reference' => ($data['instrument_reference'] ?? null) ?: $gauge?->code, 'gauge_id' => $gauge?->id]);
            $failed += $result['verdict']['ok'] === false ? 1 : 0;
        }

        $message = $rows->count().' result'.($rows->count() === 1 ? '' : 's').' imported'.($failed ? ", {$failed} out of limits" : '').'.';
        $containment = app(Containment::class);
        $message .= ' '.($containment->message($containment->apply($heat)) ?? '');
        if ($parsed['errors']) {
            $message .= ' Skipped: '.implode(' ', $parsed['errors']);
        }

        return redirect()->route('heats.show', $heat)->with('success', trim($message))->with($calibrationWarning ? ['warning' => $calibrationWarning] : []);
    }
}
