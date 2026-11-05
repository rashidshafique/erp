<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\ThermalAnalysisTest;
use App\Services\Production\Containment;
use App\Services\Quality\TestAlerts;
use App\Services\Thermal\ThermalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Phone thermal test (designs 26h to 26i): set up the cup and enter the readings from the analyser for one heat.
 * Defaults to the white cup before pouring. A white cup needs TL and solidus, a grey cup TL and both eutectic temperatures; nothing is saved without them.
 */
class MobileThermalController extends Controller
{
    public const STAGES = ['BASE' => 'Base iron', 'POST_TREATMENT' => 'After treatment', 'PRE_POUR' => 'Before pouring'];

    public const CUPS = ['STABLE' => ['Grey cup', 'Plain cup: TL, eutectic and recalescence'], 'TELLURIUM' => ['White cup', 'Tellurium cup: TL and solidus']];

    public function create(Heat $heat): View
    {
        $heat->load(['ironGrade', 'furnace']);

        return view('m.thermal', ['heat' => $heat, 'stages' => self::STAGES, 'cups' => self::CUPS, 'stage' => 'PRE_POUR', 'cup' => 'TELLURIUM']);
    }

    public function store(Request $request, Heat $heat, ThermalService $thermal, TestAlerts $alerts, Containment $containment): RedirectResponse
    {
        $white = $request->input('cup_type') === 'TELLURIUM';
        $needs = fn (bool $on) => array_merge([$on ? 'required' : 'nullable'], ['numeric', 'between:900,1400']);
        $d = $request->validate([
            'stage' => ['required', 'in:'.implode(',', array_keys(self::STAGES))],
            'cup_type' => ['required', 'in:'.implode(',', array_keys(self::CUPS))],
            'tl_c' => $needs(true), 'te_min_c' => $needs(! $white), 'te_max_c' => $needs(! $white), 'solidus_c' => $needs($white),
            'recalescence_c' => ['nullable', 'numeric', 'between:0,40'],
            'silicon' => ['nullable', 'numeric', 'between:0,6'],
            'cup_batch' => ['nullable', 'string', 'max:60'],
            'sample_reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $heat->load('ironGrade');
        try {
            $test = $thermal->recordCup($heat, $d['cup_type'], $d + ['entered_by_name' => $request->user()?->name, 'taken_by' => $request->user()?->name], '', null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['tl_c' => $e->getMessage()]);
        }
        $alerts->thermal($test->load('heat'), $request->user());
        $held = $containment->message($containment->apply($heat));

        return redirect()->route('m.tests.thermal.done', $test)->with('warning', $held);
    }

    /** The result at once: pass or fail with the reasons, and what to do next. */
    public function done(ThermalAnalysisTest $test): View
    {
        $test->load('heat.furnace');

        return view('m.thermal-done', ['test' => $test]);
    }
}
