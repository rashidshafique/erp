<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\IronGrade;
use App\Services\Production\Containment;
use App\Services\Quality\ChemistryRecorder;
use App\Services\Quality\TestAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Phone spectro entry (design 26c): the element results of a heat, judged against its grade when saved. */
class MobileSpectroController extends Controller
{
    private const COLUMNS = ['c' => 'carbon_percent', 'si' => 'silicon_percent', 'mn' => 'manganese_percent', 's' => 'sulphur_percent', 'p' => 'phosphorus_percent', 'mg' => 'magnesium_percent', 'cu' => 'copper_percent', 'cr' => 'chromium_percent', 'mo' => 'molybdenum_percent'];

    private const STAGES = ['BASE' => 'Base iron', 'AFTER_TREATMENT' => 'After treatment', 'PRE_POUR' => 'Before pouring', 'FINAL' => 'Final'];

    public function create(Heat $heat): View
    {
        $heat->load(['ironGrade', 'furnace']);

        return view('m.spectro', ['heat' => $heat, 'elements' => IronGrade::ELEMENTS, 'columns' => self::COLUMNS, 'stages' => self::STAGES]);
    }

    public function store(Request $request, Heat $heat, ChemistryRecorder $recorder, TestAlerts $alerts, Containment $containment): RedirectResponse
    {
        $pct = ['nullable', 'numeric', 'between:0,100'];
        $rules = ['sample_stage' => ['required', 'in:'.implode(',', array_keys(self::STAGES))], 'sample_reference' => ['nullable', 'string', 'max:100']];
        foreach (self::COLUMNS as $column) {
            $rules[$column] = $pct;
        }
        $data = $request->validate($rules);
        if (collect($data)->only(array_values(self::COLUMNS))->filter(fn ($x) => $x !== null && $x !== '')->isEmpty()) {
            return back()->withErrors(['chemistry' => 'Enter at least one element result.'])->withInput();
        }
        $result = $recorder->record($heat, $data + ['test_method' => 'SPECTROMETER', 'tested_by' => $request->user()?->name, 'tested_at' => now()]);
        $alerts->chemistry($heat, $result['verdict'], $request->user());
        $containment->apply($heat);

        return redirect()->route('m.home')->with('success', 'Analysis saved.');
    }
}
