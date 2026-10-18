<?php

namespace App\Http\Controllers;

use App\Models\Gauge;
use App\Models\Heat;
use App\Models\ThermalAnalysisTest;
use App\Models\ThermalCalibration;
use App\Services\Production\Containment;
use App\Services\Quality\CalibrationStatus;
use App\Services\Thermal\ThermalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class ThermalTestController extends Controller
{
    public function __construct(private ThermalService $thermal) {}

    public function index(): View
    {
        return view('thermal.index', [
            'tests' => ThermalAnalysisTest::with(['heat', 'ironGrade'])->orderByDesc('taken_at')->paginate(20),
            'relation' => $this->thermal->relation(),
            'calibration' => ThermalCalibration::orderByDesc('fitted_at')->first(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('thermal.create', [
            'heats' => Heat::with(['ironGrade', 'furnace'])->whereNotIn('status', ['CANCELLED', 'CLOSED'])->orderByDesc('created_at')->limit(60)->get(),
            'selected' => $request->query('heat_id'),
            'gauges' => Gauge::where('status', 'IN_SERVICE')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $reading = fn (string $max = '1400') => ['nullable', 'numeric', 'between:900,'.$max];
        $rules = [
            'heat_id' => ['required', 'uuid', 'exists:heats,id'],
            'gauge_id' => ['nullable', 'uuid', Rule::exists('gauges', 'id')],
            'sample_reference' => ['nullable', 'string', 'max:60'],
            'stage' => ['required', 'in:BASE,POST_TREATMENT,PRE_POUR'],
            'taken_by' => ['nullable', 'string', 'max:100'],
            'inoculant_added_pct' => ['nullable', 'numeric', 'between:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Older single-cup form fields, still accepted
            'cup_type' => ['nullable', 'in:STABLE,TELLURIUM'],
            'silicon' => ['nullable', 'numeric', 'decimal:0,4', 'between:0,6'],
            'curve_text' => ['nullable', 'string', 'max:400000'],
            'curve_file' => ['nullable', 'file', 'max:2048', 'mimes:csv,txt'],
        ];
        foreach (['', 'white.', 'grey.'] as $prefix) {
            foreach (['tl_c', 'te_min_c', 'te_max_c', 'solidus_c'] as $k) {
                $rules[$prefix.$k] = $reading();
            }
            $rules[$prefix.'peak_temp_c'] = $reading('1700');
            $rules[$prefix.'recalescence_c'] = ['nullable', 'numeric', 'between:0,40'];
        }
        foreach (['white.', 'grey.'] as $prefix) {
            $rules[$prefix.'carbon'] = ['nullable', 'numeric', 'decimal:0,4', 'between:0,5'];
            $rules[$prefix.'silicon'] = ['nullable', 'numeric', 'decimal:0,4', 'between:0,6'];
            $rules[$prefix.'curve_text'] = ['nullable', 'string', 'max:400000'];
            $rules[$prefix.'curve_file'] = ['nullable', 'file', 'max:2048', 'mimes:csv,txt'];
        }
        $data = $request->validate($rules);
        $heat = Heat::with('ironGrade')->findOrFail($data['heat_id']);
        $calibrationWarning = app(CalibrationStatus::class)->check(filled($data['gauge_id'] ?? null) ? Gauge::find($data['gauge_id']) : null, 'gauge_id');
        $shared = array_intersect_key($data, array_flip(['gauge_id', 'sample_reference', 'stage', 'taken_by', 'inoculant_added_pct', 'notes']));

        // One block per cup. The twin-cup form sends white[...] and grey[...]; the older form sends flat fields for one cup.
        $blocks = [];
        foreach (['white' => 'TELLURIUM', 'grey' => 'STABLE'] as $key => $cup) {
            $vals = $data[$key] ?? [];
            $text = $vals['curve_text'] ?? '';
            if ($request->hasFile("{$key}.curve_file")) {
                $text = file_get_contents($request->file("{$key}.curve_file")->getRealPath());
            }
            $filled = collect($vals)->except(['curve_text', 'curve_file'])->filter(fn ($x) => $x !== null && $x !== '')->isNotEmpty() || trim((string) $text) !== '';
            if ($filled) {
                $blocks[] = [$cup, $vals + $shared, $text, $key];
            }
        }
        if (! $blocks) {
            $text = $data['curve_text'] ?? '';
            if ($request->hasFile('curve_file')) {
                $text = file_get_contents($request->file('curve_file')->getRealPath());
            }
            $vals = array_intersect_key($data, array_flip(['tl_c', 'te_min_c', 'te_max_c', 'recalescence_c', 'solidus_c', 'peak_temp_c', 'silicon']));
            $any = collect($vals)->except('silicon')->filter(fn ($x) => $x !== null && $x !== '')->isNotEmpty();
            if (! $any && trim($text) === '') {
                throw ValidationException::withMessages(['curve_text' => 'Enter the readings from the thermal analyser, or paste or upload the cooling curve, for at least one cup.']);
            }
            $blocks[] = [$data['cup_type'] ?? 'STABLE', $vals + $shared, $text, null];
        }

        $group = count($blocks) > 1 ? (string) Str::uuid() : null;
        // The grey cup has no silicon of its own on the form, so it uses the white cup's when entered.
        $si = collect($blocks)->map(fn ($b) => $b[1]['silicon'] ?? null)->first(fn ($x) => $x !== null && $x !== '');
        $first = null;
        try {
            DB::transaction(function () use ($blocks, $heat, $group, $si, &$first) {
                foreach ($blocks as [$cup, $vals, $text, $key]) {
                    if (($vals['silicon'] ?? null) === null || $vals['silicon'] === '') {
                        $vals['silicon'] = $si;
                    }
                    $t = $this->thermal->recordCup($heat, $cup, $vals, $text, $group);
                    $first ??= $t;
                }
            });
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['curve_text' => $e->getMessage()]);
        }

        $n = count($blocks);
        $containment = app(Containment::class);
        $held = $containment->message($containment->apply($heat));

        return redirect()->route('thermal.show', $first)->with('warning', trim($held.' '.$calibrationWarning))->with('success', $n > 1 ? "Both cups saved. White cup: {$this->resultOf($first, 'TELLURIUM')}; grey cup: {$this->resultOf($first, 'STABLE')}." : "Test saved: {$first->result}.");
    }

    private function resultOf(ThermalAnalysisTest $t, string $cup): string
    {
        $row = $t->cup_type === $cup ? $t : $t->siblings()->where('cup_type', $cup)->first();

        return $row ? str_replace('_', ' ', $row->result) : 'not recorded';
    }

    public function show(ThermalAnalysisTest $test): View
    {
        $test->load(['heat.furnace', 'ironGrade']);
        $m = ['te_min_c' => $test->te_min_c !== null ? (float) $test->te_min_c : null];

        return view('thermal.show', [
            'test' => $test,
            'advice' => $this->thermal->inoculantAdvice($m, $test->ironGrade, $test->cup_type),
        ]);
    }

    public function calibrate(): RedirectResponse
    {
        try {
            $cal = $this->thermal->calibrate();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['calibrate' => $e->getMessage()]);
        }

        return back()->with('success', "Calibrated on {$cal->sample_count} heats (R² {$cal->r_squared}).");
    }

    public function sample()
    {
        // A small built-in sample so the page can be tried without a thermocouple file.
        $rows = ['time_s,temp_c'];
        foreach ($this->sampleCurve() as [$t, $T]) {
            $rows[] = "{$t},{$T}";
        }

        return response(implode("\n", $rows), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="sample-cooling-curve.csv"']);
    }

    private function sampleCurve(): array
    {
        $T = 1320.0;
        $k = 0.0095;
        $fa = 0.0;
        $fe = 0.0;
        $out = [];
        for ($t = 0.0; $t <= 600.0; $t += 0.5) {
            $out[] = [round($t, 1), round($T, 1)];
            $d = -$k * ($T - 300) * 0.5;
            if ($T < 1195 && $fa < 0.22 && $fe == 0.0) {
                $x = min(0.22 - $fa, 0.0024 * (1195 - $T) * 0.5);
                $fa += $x;
                $d += 55 * $x / 0.22;
            }
            if ($T < 1150 && $fe < 0.78 && ($fe > 0 || $T < 1142)) {
                $x = min(0.78 - $fe, 0.0075 * (1150 - $T) * 0.5);
                $fe += $x;
                $d += 190 * $x / 0.78;
            }
            $T += $d;
        }

        return $out;
    }
}
