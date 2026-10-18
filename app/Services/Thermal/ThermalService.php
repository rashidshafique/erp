<?php

namespace App\Services\Thermal;

use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\ThermalAnalysisTest;
use App\Models\ThermalCalibration;
use App\Models\ThermalWindow;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ThermalService
{
    public function __construct(private CurveAnalyser $analyser) {}

    /** Active relation TL -> CE for a cup type: the latest fit, or the configured default. */
    public function relation(string $cup = 'STABLE'): array
    {
        $cal = ThermalCalibration::where('cup_type', $cup)->orderByDesc('fitted_at')->first();
        if ($cal) {
            return ['a' => (float) $cal->tl_intercept_c, 'b' => (float) $cal->tl_slope_c_per_ce, 'calibrated' => true, 'n' => $cal->sample_count,
                'gain' => $cal->inoculant_gain_c_per_0p1pct !== null ? (float) $cal->inoculant_gain_c_per_0p1pct : null];
        }

        return ['a' => (float) config('foundry.thermal.tl_intercept_c'), 'b' => (float) config('foundry.thermal.tl_slope_c_per_ce'), 'calibrated' => false, 'n' => 0, 'gain' => null];
    }

    public function ceFromTl(float $tl, string $cup = 'STABLE'): float
    {
        $r = $this->relation($cup);

        return round(($r['a'] - $tl) / $r['b'], 2);
    }

    /** Parse "seconds,temperature" lines (comma, semicolon, tab or space separated; header allowed). */
    public function parseCurve(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\R/', trim($text)) as $line) {
            $parts = preg_split('/[,;\t ]+/', trim($line));
            if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $rows[] = [(float) $parts[0], (float) $parts[1]];
            }
        }
        if (count($rows) < 40) {
            throw new InvalidArgumentException('Found '.count($rows).' readable rows; at least 40 "seconds,temperature" rows are needed.');
        }

        return $rows;
    }

    /** Analyse a curve and judge it. Returns the attributes to store on a ThermalAnalysisTest. */
    public function evaluate(array $curve, ?IronGrade $grade, string $cup = 'STABLE', ?float $silicon = null): array
    {
        $r = $this->analyser->analyse($curve);
        $ce = $r['tl_c'] !== null ? $this->ceFromTl($r['tl_c'], $cup) : null;
        $carbon = ($ce !== null && $silicon !== null) ? round($ce - $silicon / 3, 4) : null;

        $attrs = [
            'tl_c' => $r['tl_c'], 'te_min_c' => $r['te_min_c'], 'te_max_c' => $r['te_max_c'],
            'recalescence_c' => $r['recalescence_c'], 'solidus_c' => $r['solidus_c'],
            'ce_from_tl' => $ce, 'carbon_from_tl' => $carbon, 'silicon_used' => $silicon,
        ];
        $judged = $this->judge($attrs, $grade, $cup);
        $judged['findings'] = array_merge($r['warnings'], $judged['findings']);

        return $attrs + $judged + ['warnings' => $r['warnings']];
    }

    /** @return array{result: string, findings: array} */
    /**
     * Judges one cup against its grade's window. The white (tellurium) cup is judged on TL, CE and solidus only, since it has no
     * eutectic arrest; the grey (stable) cup on all five. $ceOnWhiteCup leaves the CE check to the white cup of the same sample.
     */
    public function judge(array $m, ?IronGrade $grade, string $cup = 'STABLE', bool $ceOnWhiteCup = false): array
    {
        if (! $grade) {
            return ['result' => 'NOT_JUDGED', 'findings' => ['No iron grade is set on the heat, so the test cannot be judged.']];
        }
        $w = ThermalWindow::where('iron_grade_id', $grade->id)->where('cup_type', $cup)->first();
        if (! $w || $w->isEmpty()) {
            return ['result' => 'NOT_JUDGED', 'findings' => ["No acceptance window is set for {$grade->code} with a {$cup} cup."]];
        }

        $f = [];
        $check = function (string $label, $value, $min, $max, string $unit = '') use (&$f) {
            if ($min === null && $max === null) {
                return;
            }
            if ($value === null) {
                $f[] = "{$label} was not measured but a limit is set.";

                return;
            }
            if ($min !== null && $value < (float) $min) {
                $f[] = "{$label} {$value}{$unit} is below the minimum {$min}{$unit}.";
            }
            if ($max !== null && $value > (float) $max) {
                $f[] = "{$label} {$value}{$unit} is above the maximum {$max}{$unit}.";
            }
        };
        $check('TL', $m['tl_c'], $w->tl_min_c, $w->tl_max_c, ' °C');
        if (! $ceOnWhiteCup) {
            $check('CE from TL', $m['ce_from_tl'], $w->ce_min, $w->ce_max);
        }
        if ($cup !== 'TELLURIUM') {
            $check('TEmin', $m['te_min_c'], $w->te_min_floor_c, null, ' °C');
            $check('Recalescence', $m['recalescence_c'], $w->recal_min_c, $w->recal_max_c, ' °C');
        }
        $check('Solidus', $m['solidus_c'], $w->solidus_min_c, null, ' °C');

        return ['result' => $f ? 'FAIL' : 'PASS', 'findings' => $f];
    }

    /** Inoculant advice when TEmin is under the floor. */
    public function inoculantAdvice(array $m, ?IronGrade $grade, string $cup = 'STABLE'): ?array
    {
        if (! $grade) {
            return null;
        }
        $w = ThermalWindow::where('iron_grade_id', $grade->id)->where('cup_type', $cup)->first();
        if (! $w || $w->te_min_floor_c === null || $m['te_min_c'] === null) {
            return null;
        }
        $rel = $this->relation($cup);
        $gain = $rel['gain'] ?? (float) config('foundry.thermal.default_inoculant_gain_c_per_0p1pct');
        $deficit = round((float) $w->te_min_floor_c - (float) $m['te_min_c'], 1);
        if ($deficit <= 0) {
            return ['needed' => false, 'deficit_c' => 0.0, 'suggest_pct' => 0.0, 'capped' => false, 'calibrated' => $rel['gain'] !== null, 'gain' => $gain];
        }
        $raw = $deficit / $gain * 0.1;
        $cap = (float) config('foundry.thermal.max_inoculant_pct');

        return ['needed' => true, 'deficit_c' => $deficit, 'suggest_pct' => round(min($raw, $cap), 2), 'capped' => $raw > $cap, 'calibrated' => $rel['gain'] !== null, 'gain' => $gain];
    }

    /** Create a test for a heat from a pasted/uploaded curve. */
    public function record(Heat $heat, string $curveText, array $meta): ThermalAnalysisTest
    {
        return $this->recordCup($heat, $meta['cup_type'] ?? 'STABLE', $meta, $curveText);
    }

    /** Create a test from readings only (no curve). Accepts tl_c, te_min_c, te_max_c, recalescence_c, solidus_c, peak_temp_c. */
    public function recordManual(Heat $heat, array $values, array $meta): ThermalAnalysisTest
    {
        return $this->recordCup($heat, $meta['cup_type'] ?? 'STABLE', $values + $meta, null);
    }

    /**
     * Record one cup. $v holds optional keyed readings (tl_c, te_min_c, te_max_c, recalescence_c, solidus_c, peak_temp_c,
     * carbon, silicon) plus sample details. A curve is optional: when given it is analysed, and any reading keyed in
     * (as shown by the analyser) takes precedence over the value detected from the curve.
     * A tellurium (white) cup gives TL, solidus and peak only; its eutectic values are not used.
     */
    public function recordCup(Heat $heat, string $cup, array $v, ?string $curveText, ?string $sampleGroupId = null): ThermalAnalysisTest
    {
        $num = fn ($k) => isset($v[$k]) && $v[$k] !== '' && $v[$k] !== null ? round((float) $v[$k], 1) : null;
        $white = $cup === 'TELLURIUM';
        $curve = [];
        $det = ['tl_c' => null, 'te_min_c' => null, 'te_max_c' => null, 'recalescence_c' => null, 'solidus_c' => null, 'peak_temp_c' => null];
        $findings = [];
        if ($curveText !== null && trim($curveText) !== '') {
            $curve = $this->parseCurve($curveText);
            $r = $this->analyser->analyse($curve);
            $det = ['tl_c' => $r['tl_c'], 'te_min_c' => $r['te_min_c'], 'te_max_c' => $r['te_max_c'],
                'recalescence_c' => $r['recalescence_c'], 'solidus_c' => $r['solidus_c'], 'peak_temp_c' => round(max(array_column($curve, 1)), 1)];
            if ($white) {
                $det['te_min_c'] = $det['te_max_c'] = $det['recalescence_c'] = null;
            }
            $findings = $r['warnings'];
        }

        $names = ['tl_c' => 'TL', 'te_min_c' => 'TEmin', 'te_max_c' => 'TEmax', 'solidus_c' => 'TS', 'peak_temp_c' => 'Peak'];
        $m = [];
        $keyed = [];
        foreach (['tl_c', 'te_min_c', 'te_max_c', 'recalescence_c', 'solidus_c', 'peak_temp_c'] as $k) {
            $keyed_v = ($white && in_array($k, ['te_min_c', 'te_max_c', 'recalescence_c'], true)) ? null : $num($k);
            $m[$k] = $keyed_v ?? $det[$k];
            if ($keyed_v !== null) {
                $keyed[$k] = true;
            }
            if ($keyed_v !== null && $det[$k] !== null && isset($names[$k]) && abs($keyed_v - (float) $det[$k]) > 3.0) {
                $findings[] = "{$names[$k]} keyed in as {$keyed_v} °C differs from the curve ({$det[$k]} °C) by ".round(abs($keyed_v - $det[$k]), 1).' °C; the keyed value is used.';
            }
        }

        // Consistency checks apply when at least one of the two values was keyed in; curve-detected pairs are left to the analyser.
        $pair = fn ($a, $b) => $m[$a] !== null && $m[$b] !== null && (isset($keyed[$a]) || isset($keyed[$b]));

        if ($m['tl_c'] === null && $m['te_min_c'] === null && $m['solidus_c'] === null) {
            throw new InvalidArgumentException('Enter at least TL, TEmin or TS (solidus) from the thermal analyser, or add the cooling curve.');
        }
        if ($pair('te_min_c', 'te_max_c') && $m['te_max_c'] < $m['te_min_c']) {
            throw new InvalidArgumentException('TEmax cannot be lower than TEmin.');
        }
        if ($pair('tl_c', 'te_max_c') && $m['tl_c'] < $m['te_max_c']) {
            throw new InvalidArgumentException('TL must be above the eutectic temperature (TEmax).');
        }
        if ($pair('solidus_c', 'te_min_c') && $m['solidus_c'] > $m['te_min_c']) {
            throw new InvalidArgumentException('TS (solidus) cannot be above TEmin.');
        }
        if ($pair('tl_c', 'solidus_c') && $m['solidus_c'] >= $m['tl_c']) {
            throw new InvalidArgumentException('TS (solidus) must be below TL.');
        }
        if ($pair('peak_temp_c', 'tl_c') && $m['peak_temp_c'] < $m['tl_c']) {
            throw new InvalidArgumentException('The peak temperature cannot be below TL.');
        }
        if ($m['recalescence_c'] === null && $m['te_min_c'] !== null && $m['te_max_c'] !== null) {
            $m['recalescence_c'] = round($m['te_max_c'] - $m['te_min_c'], 1);
        }

        // The white cup of the same sample is the better source of carbon, silicon and CE, so a grey cup takes them from it when it has them.
        $whiteCup = $cup === 'STABLE' && $sampleGroupId
            ? ThermalAnalysisTest::where('sample_group_id', $sampleGroupId)->where('cup_type', 'TELLURIUM')->first()
            : null;
        $si = isset($v['silicon']) && $v['silicon'] !== '' && $v['silicon'] !== null ? (float) $v['silicon'] : $this->latestSilicon($heat);
        $carbonReported = isset($v['carbon']) && $v['carbon'] !== '' && $v['carbon'] !== null ? round((float) $v['carbon'], 4) : null;
        if ($whiteCup?->silicon_used !== null) {
            $si = (float) $whiteCup->silicon_used;
        }
        if ($whiteCup?->carbon_reported_pct !== null) {
            $carbonReported = (float) $whiteCup->carbon_reported_pct;
        }
        $ce = $m['tl_c'] !== null ? $this->ceFromTl($m['tl_c'], $cup) : null;
        $carbon = ($ce !== null && $si !== null) ? round($ce - $si / 3, 4) : null;
        if ($whiteCup?->ce_from_tl !== null) {
            $ce = (float) $whiteCup->ce_from_tl;
            $carbon = $whiteCup->carbon_from_tl !== null ? (float) $whiteCup->carbon_from_tl : $carbon;
            $findings[] = "CE {$ce} and carbon from TL are taken from the white cup of this sample.";
        }

        $judged = $this->judge($m + ['ce_from_tl' => $ce], $heat->ironGrade, $cup, $this->whiteCupJudgesCe($heat, $cup, $sampleGroupId));
        if (! $curve) {
            $findings[] = 'Values were keyed in manually; no cooling curve is stored, so the picked points could not be checked.';
        }

        return ThermalAnalysisTest::create([
            'heat_id' => $heat->id,
            'iron_grade_id' => $heat->iron_grade_id,
            'gauge_id' => $v['gauge_id'] ?? null,
            'sample_reference' => $v['sample_reference'] ?? null,
            'sample_group_id' => $sampleGroupId,
            'stage' => $v['stage'] ?? 'POST_TREATMENT',
            'cup_type' => $cup,
            'taken_at' => $v['taken_at'] ?? now(),
            'taken_by' => $v['taken_by'] ?? null,
            'curve' => $curve,
            'entry_mode' => $curve ? 'CURVE' : 'MANUAL',
            'tl_c' => $m['tl_c'], 'te_min_c' => $m['te_min_c'], 'te_max_c' => $m['te_max_c'],
            'recalescence_c' => $m['recalescence_c'], 'solidus_c' => $m['solidus_c'], 'peak_temp_c' => $m['peak_temp_c'],
            'ce_from_tl' => $ce, 'carbon_from_tl' => $carbon, 'silicon_used' => $si, 'carbon_reported_pct' => $carbonReported,
            'result' => $judged['result'], 'findings' => array_merge($findings, $judged['findings']),
            'inoculant_added_pct' => $v['inoculant_added_pct'] ?? null,
            'notes' => $v['notes'] ?? null,
        ]);
    }

    /** True when this grey cup comes with a white cup whose window sets a CE limit: CE is then judged on the white cup. */
    private function whiteCupJudgesCe(Heat $heat, string $cup, ?string $sampleGroupId): bool
    {
        if ($cup !== 'STABLE' || ! $sampleGroupId || ! $heat->iron_grade_id) {
            return false;
        }
        $white = ThermalWindow::where('iron_grade_id', $heat->iron_grade_id)->where('cup_type', 'TELLURIUM')->first();

        return $white !== null && ($white->ce_min !== null || $white->ce_max !== null)
            && ThermalAnalysisTest::where('sample_group_id', $sampleGroupId)->where('cup_type', 'TELLURIUM')->whereNotNull('tl_c')->exists();
    }

    public function latestSilicon(Heat $heat): ?float
    {
        $v = $heat->chemistryReadings()->whereNotNull('silicon_percent')->orderByDesc('reading_number')->value('silicon_percent');

        return $v !== null ? (float) $v : null;
    }

    /**
     * Fit TL = a - b*CE from tests whose heat has a chemistry reading with a known CE.
     * Needs at least 8 pairs and a spread of CE of at least 0.15.
     */
    public function calibrate(string $cup = 'STABLE'): ThermalCalibration
    {
        $pairs = [];
        $tests = ThermalAnalysisTest::where('cup_type', $cup)->whereNotNull('tl_c')->get();
        foreach ($tests->groupBy('heat_id') as $heatId => $group) {
            $reading = DB::table('heat_chemistry_readings')->where('heat_id', $heatId)
                ->where(fn ($q) => $q->whereNotNull('carbon_equivalent')->orWhere(fn ($q2) => $q2->whereNotNull('carbon_percent')->whereNotNull('silicon_percent')))
                ->orderByDesc('reading_number')->first();
            if (! $reading) {
                continue;
            }
            $ce = $reading->carbon_equivalent !== null
                ? (float) $reading->carbon_equivalent
                : (float) $reading->carbon_percent + (float) $reading->silicon_percent / 3 + ($reading->phosphorus_percent !== null ? (float) $reading->phosphorus_percent / 3 : 0);
            $pairs[] = [$ce, (float) $group->sortByDesc('taken_at')->first()->tl_c];
        }

        $n = count($pairs);
        if ($n < 8) {
            throw new InvalidArgumentException("Calibration needs at least 8 heats with both a thermal test (TL) and a chemistry reading; found {$n}.");
        }
        $xs = array_column($pairs, 0);
        $ys = array_column($pairs, 1);
        if (max($xs) - min($xs) < 0.15) {
            throw new InvalidArgumentException('The CE values are too close together to fit a line. Include heats with a wider CE range.');
        }
        $mx = array_sum($xs) / $n;
        $my = array_sum($ys) / $n;
        $sxx = $sxy = $syy = 0.0;
        foreach ($pairs as [$x, $y]) {
            $sxx += ($x - $mx) ** 2;
            $sxy += ($x - $mx) * ($y - $my);
            $syy += ($y - $my) ** 2;
        }
        $beta = $sxy / $sxx;                // °C per CE, negative
        if ($beta >= 0) {
            throw new InvalidArgumentException('The data show TL rising with CE, which is not physical. Check the chemistry and cups.');
        }
        $alpha = $my - $beta * $mx;
        $sse = 0.0;
        foreach ($pairs as [$x, $y]) {
            $sse += ($y - ($alpha + $beta * $x)) ** 2;
        }
        $r2 = $syy > 0 ? 1 - $sse / $syy : null;
        $sdTl = sqrt($sse / max(1, $n - 2));

        return ThermalCalibration::create([
            'cup_type' => $cup, 'tl_intercept_c' => round($alpha, 3), 'tl_slope_c_per_ce' => round(-$beta, 3),
            'sample_count' => $n, 'r_squared' => $r2 !== null ? round($r2, 4) : null,
            'residual_sd_ce' => round($sdTl / abs($beta), 4), 'fitted_at' => now(),
        ]);
    }

    /** Latest test for a heat in one of the stages that decide a pour (default: any). */
    public function latestTest(Heat $heat): ?ThermalAnalysisTest
    {
        return $heat->thermalTests()->first();
    }
}
