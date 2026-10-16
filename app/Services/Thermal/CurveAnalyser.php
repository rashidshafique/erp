<?php

namespace App\Services\Thermal;

use InvalidArgumentException;

/**
 * Reads the characteristic points off an iron cooling curve.
 *
 * Input: samples as [seconds, temperature °C]. Output: TL (liquidus arrest), TEmin and TEmax
 * (eutectic undercooling and recalescence peak), recalescence, solidus.
 *
 * Method: resample to a uniform step, smooth, then work on the first and second time derivative.
 * - The eutectic arrest is the point of slowest cooling (highest dT/dt) after the fastest cooling.
 * - TEmin is the temperature minimum just before it; TEmax the peak after it.
 * - TL is the largest flattening of the curve (second derivative peak) before the eutectic starts.
 * - Solidus is the largest steepening (second derivative minimum) after the eutectic.
 *
 * Limits: a very strong primary arrest (high-carbon hypereutectic iron) can be mistaken for the
 * eutectic. The result lists warnings so a person confirms the picked points on the chart.
 */
class CurveAnalyser
{
    public function __construct(private int $smoothing = 5)
    {
    }

    /** @param array<int, array{0: float|int, 1: float|int}> $samples */
    public function analyse(array $samples): array
    {
        $samples = $this->clean($samples);
        if (count($samples) < 40) {
            throw new InvalidArgumentException('The curve needs at least 40 readings.');
        }

        [$t, $T] = $this->resample($samples);
        $n = count($T);
        if (max($T) < 1130.0) {
            throw new InvalidArgumentException('The curve never reaches the liquidus range (below 1130 °C at its highest).');
        }
        if (min($T) > 1100.0) {
            throw new InvalidArgumentException('The curve stops above 1100 °C, before solidification is complete.');
        }
        $dt = $t[1] - $t[0];

        $S = $this->smooth($T, $this->smoothing);
        $r = $this->derivative($S, $dt);
        $r = $this->smooth($r, $this->smoothing);
        $d2 = $this->smooth($this->derivative($r, $dt), $this->smoothing);

        $warnings = [];

        // Fastest cooling, searched in the first 60 % of the record.
        $limit = (int) floor($n * 0.6);
        $i0 = 0;
        for ($i = 1; $i < $limit; $i++) {
            if ($r[$i] < $r[$i0]) {
                $i0 = $i;
            }
        }
        $margin = max(2, (int) round(3 / $dt));

        // Eutectic arrest: least negative rate after the fastest cooling, only below 1180 °C.
        $ie = null;
        for ($i = $i0 + $margin; $i < $n - $margin; $i++) {
            if ($S[$i] > 1180.0) {
                continue;
            }
            if ($ie === null || $r[$i] > $r[$ie]) {
                $ie = $i;
            }
        }
        if ($ie === null || $r[$ie] < -1.2) {
            throw new InvalidArgumentException('No eutectic arrest was found. Check the sample, the cup and the thermocouple.');
        }

        // Walk to the temperature minimum that precedes the arrest.
        $imin = $ie;
        $recalescence = $r[$ie] > 0.02;
        if ($recalescence) {
            $from = max($i0, $ie - (int) round(40 / $dt));
            $imin = $from;
            for ($i = $from; $i <= $ie; $i++) {
                if ($S[$i] < $S[$imin]) {
                    $imin = $i;
                }
            }
            $imax = $ie;
            for ($i = $ie; $i < $n - 1 && $r[$i] > 0; $i++) {
                $imax = $i + 1;
            }
            // Peak of the smoothed curve within the rising region.
            for ($i = $imin; $i <= min($n - 1, $imax); $i++) {
                if ($S[$i] > $S[$imax]) {
                    $imax = $i;
                }
            }
        } else {
            $imax = $ie;
        }

        // Refine on the raw readings close to the smoothed picks, so smoothing does not blunt the peaks.
        $near = max(2, $this->smoothing);
        $teMin = $this->extreme($T, $imin, $near, false);
        $teMax = $this->extreme($T, $imax, $near, true);
        if (! $recalescence) {
            $teMax = $teMin;
        }
        $recal = max(0.0, $teMax - $teMin);

        // Eutectic onset: strongest flattening in the 40 s that lead to the minimum.
        $w = (int) round(40 / $dt);
        $eo = max($i0 + $margin, $imin - $w);
        for ($i = $eo; $i <= $imin; $i++) {
            if ($d2[$i] > $d2[$eo]) {
                $eo = $i;
            }
        }

        // Liquidus: largest flattening between the fastest cooling and the eutectic onset.
        $tl = null;
        $tlIndex = null;
        $a = $i0 + $margin;
        $b = $eo - max($margin, (int) round(8 / $dt));
        if ($b > $a) {
            $best = $a;
            for ($i = $a; $i <= $b; $i++) {
                if ($d2[$i] > $d2[$best]) {
                    $best = $i;
                }
            }
            if ($d2[$best] > 0.0015 && $best > $a && $best < $b) {
                $tl = $S[$best];
                $tlIndex = $best;
            }
        }
        if ($tl === null) {
            $warnings[] = 'No clear liquidus arrest was found. TL, CE from TL and carbon from TL are not reported.';
        } elseif ($tl < $teMax + 3) {
            $warnings[] = 'The liquidus arrest lies too close to the eutectic. Check the picked points.';
            $tl = null;
            $tlIndex = null;
        }

        // Solidus: strongest steepening after the eutectic peak.
        $solidus = null;
        $solidusIndex = null;
        $c = min($n - 1, $imax + $margin);
        $end = $n - $margin;
        if ($end > $c) {
            $best = $c;
            for ($i = $c; $i < $end; $i++) {
                if ($d2[$i] < $d2[$best]) {
                    $best = $i;
                }
            }
            if ($d2[$best] < -0.0015 && $S[$best] < $teMax) {
                $solidus = $S[$best];
                $solidusIndex = $best;
            }
        }
        if ($solidus === null) {
            $warnings[] = 'No clear end of solidification was found, so the solidus is not reported.';
        }

        return [
            'tl_c' => $tl !== null ? round($tl, 1) : null,
            'te_min_c' => round($teMin, 1),
            'te_max_c' => round($teMax, 1),
            'recalescence_c' => round($recal, 1),
            'solidus_c' => $solidus !== null ? round($solidus, 1) : null,
            'index' => ['tl' => $tlIndex, 'te_min' => $imin, 'te_max' => $imax, 'solidus' => $solidusIndex],
            'duration_s' => round($t[$n - 1] - $t[0], 1),
            'step_s' => round($dt, 3),
            'warnings' => $warnings,
        ];
    }

    private function extreme(array $v, int $centre, int $radius, bool $max): float
    {
        $lo = max(0, $centre - $radius);
        $hi = min(count($v) - 1, $centre + $radius);
        $best = $v[$lo];
        for ($i = $lo; $i <= $hi; $i++) {
            $best = $max ? max($best, $v[$i]) : min($best, $v[$i]);
        }

        return $best;
    }

    private function clean(array $samples): array
    {
        $out = [];
        foreach ($samples as $row) {
            if (! is_array($row) || count($row) < 2 || ! is_numeric($row[0]) || ! is_numeric($row[1])) {
                continue;
            }
            $out[] = [(float) $row[0], (float) $row[1]];
        }
        usort($out, fn ($a, $b) => $a[0] <=> $b[0]);

        // Drop duplicated times.
        $unique = [];
        foreach ($out as $row) {
            if ($unique && abs($row[0] - $unique[count($unique) - 1][0]) < 1e-9) {
                continue;
            }
            $unique[] = $row;
        }

        return $unique;
    }

    /** Linear resample onto a uniform time grid using the median sampling step. */
    private function resample(array $s): array
    {
        $steps = [];
        for ($i = 1; $i < count($s); $i++) {
            $steps[] = $s[$i][0] - $s[$i - 1][0];
        }
        sort($steps);
        $dt = max(0.05, $steps[intdiv(count($steps), 2)]);
        $t0 = $s[0][0];
        $tEnd = $s[count($s) - 1][0];
        $t = [];
        $T = [];
        $j = 0;
        for ($x = $t0; $x <= $tEnd + 1e-9; $x += $dt) {
            while ($j < count($s) - 2 && $s[$j + 1][0] < $x) {
                $j++;
            }
            [$xa, $ya] = $s[$j];
            [$xb, $yb] = $s[$j + 1];
            $f = $xb == $xa ? 0.0 : ($x - $xa) / ($xb - $xa);
            $f = max(0.0, min(1.0, $f));
            $t[] = $x;
            $T[] = $ya + ($yb - $ya) * $f;
        }

        return [$t, $T];
    }

    private function smooth(array $v, int $window): array
    {
        $n = count($v);
        $h = intdiv(max(1, $window), 2);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $lo = max(0, $i - $h);
            $hi = min($n - 1, $i + $h);
            $sum = 0.0;
            for ($k = $lo; $k <= $hi; $k++) {
                $sum += $v[$k];
            }
            $out[] = $sum / ($hi - $lo + 1);
        }

        return $out;
    }

    private function derivative(array $v, float $dt): array
    {
        $n = count($v);
        $out = array_fill(0, $n, 0.0);
        for ($i = 1; $i < $n - 1; $i++) {
            $out[$i] = ($v[$i + 1] - $v[$i - 1]) / (2 * $dt);
        }
        $out[0] = $out[1];
        $out[$n - 1] = $out[$n - 2];

        return $out;
    }
}
