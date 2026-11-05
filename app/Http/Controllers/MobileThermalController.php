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

    /** Why a test is thrown out. Stopped, bad sample and something else can also be logged before any reading exists, when the test never finished. */
    public const INVALID = [
        'STOPPED' => 'The analyser stopped responding',
        'BAD_SAMPLE' => 'Bad sample or pour',
        'WRONG_ENTRY' => 'Wrong reading entered',
        'WRONG_HEAT' => 'Tested the wrong heat',
        'OTHER' => 'Something else',
    ];

    private const UNFINISHED = ['STOPPED', 'BAD_SAMPLE', 'OTHER'];

    public function create(Request $request, Heat $heat): View
    {
        $heat->load(['ironGrade', 'furnace']);

        return view('m.thermal', [
            'heat' => $heat, 'stages' => self::STAGES, 'cups' => self::CUPS, 'stage' => $this->pick($request->query('stage'), self::STAGES, 'PRE_POUR'), 'cup' => $this->pick($request->query('cup'), self::CUPS, 'TELLURIUM'),
            'unfinished' => array_intersect_key(self::INVALID, array_flip(self::UNFINISHED)),
        ]);
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

        return view('m.thermal-done', ['test' => $test, 'reasons' => self::INVALID]);
    }

    /** A saved test that turns out wrong: voided with a reason (it stops counting for gates and certificates), then the same cup is set up again. */
    public function invalid(Request $request, ThermalAnalysisTest $test): RedirectResponse
    {
        abort_if($test->isVoided(), 422, 'This test is already marked invalid.');
        $d = $this->invalidInput($request, array_keys(self::INVALID));
        $test->update(['voided_at' => now(), 'void_reason' => $this->reasonText($d), 'voided_by_name' => $request->user()?->name]);

        return $this->retake($test->heat_id, $test->stage, $test->cup_type);
    }

    /** A test that never finished (the analyser froze, the cup was poured wrong): kept as an invalid attempt with no readings, so the heat shows what happened. */
    public function unfinished(Request $request, Heat $heat): RedirectResponse
    {
        $d = $this->invalidInput($request, self::UNFINISHED) + $request->validate([
            'stage' => ['required', 'in:'.implode(',', array_keys(self::STAGES))],
            'cup_type' => ['required', 'in:'.implode(',', array_keys(self::CUPS))],
            'cup_batch' => ['nullable', 'string', 'max:60'],
        ]);
        $who = $request->user()?->name;
        ThermalAnalysisTest::create([
            'heat_id' => $heat->id, 'iron_grade_id' => $heat->iron_grade_id, 'stage' => $d['stage'], 'cup_type' => $d['cup_type'], 'cup_batch' => $d['cup_batch'] ?? null,
            'taken_at' => now(), 'curve' => [], 'result' => 'NOT_JUDGED', 'entered_by_name' => $who, 'taken_by' => $who,
            'voided_at' => now(), 'void_reason' => $this->reasonText($d), 'voided_by_name' => $who,
        ]);

        return $this->retake($heat->id, $d['stage'], $d['cup_type']);
    }

    /** @param array<int, string> $allowed */
    private function invalidInput(Request $request, array $allowed): array
    {
        return $request->validate([
            'reason' => ['required', 'in:'.implode(',', $allowed)],
            'note' => ['nullable', 'string', 'max:300', 'required_if:reason,OTHER'],
        ], ['reason.required' => 'Choose what went wrong.', 'note.required_if' => 'Say what went wrong.']);
    }

    private function reasonText(array $d): string
    {
        return self::INVALID[$d['reason']].(filled($d['note'] ?? null) ? ': '.trim($d['note']) : '');
    }

    private function retake(string $heatId, ?string $stage, ?string $cup): RedirectResponse
    {
        return redirect()->route('m.tests.thermal', ['heat' => $heatId, 'stage' => $stage, 'cup' => $cup])->with('notice', 'Marked invalid. Set up the test again when you are ready.');
    }

    /** @param array<string, mixed> $options */
    private function pick(?string $value, array $options, string $default): string
    {
        return $value !== null && array_key_exists($value, $options) ? $value : $default;
    }
}
