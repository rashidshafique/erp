<?php

namespace Tests\Support;

/**
 * Generates a cooling curve from a simple heat balance so the analyser can be checked
 * against known events. Not a metallurgical model; it only needs realistic arrests.
 */
class CurveSimulator
{
    /** @return array{curve: array, truth: array} */
    public static function make(float $tl = 1195.0, float $eutectic = 1150.0, float $undercool = 8.0, float $dt = 0.2, float $noise = 0.0, int $seed = 1): array
    {
        mt_srand($seed);
        $T = 1320.0;
        $ambient = 300.0;
        $k = 0.0095;           // 1/s heat loss
        $latentA = 55.0;       // °C temperature equivalent of austenite latent heat
        $latentE = 190.0;      // eutectic
        $fa = 0.0;             // austenite fraction of the total latent budget
        $fe = 0.0;
        $maxA = 0.22;
        $curve = [];
        $teMinTruth = null;
        $eutStart = null;
        $t = 0.0;
        $end = 900.0;
        while ($t <= $end) {
            $curve[] = [round($t, 3), round($T + ($noise ? (mt_rand() / mt_getrandmax() - 0.5) * 2 * $noise : 0), 2)];
            $dTloss = -$k * ($T - $ambient) * $dt;
            $dTlat = 0.0;
            if ($T < $tl && $fa < $maxA && $fe == 0.0) {
                $dfa = min($maxA - $fa, 0.0024 * ($tl - $T) * $dt);
                $fa += $dfa;
                $dTlat += $latentA * $dfa / $maxA;
            }
            if ($T < $eutectic - $undercool * 0.0 && $fe < 1 - $maxA) {
                $under = max(0.0, ($eutectic - $T));
                if ($under > 0 && ($fe > 0 || $T < $eutectic - $undercool)) {
                    if ($eutStart === null) {
                        $eutStart = $t;
                    }
                    $dfe = min(1 - $maxA - $fe, 0.0075 * $under * $dt);
                    $fe += $dfe;
                    $dTlat += $latentE * $dfe / (1 - $maxA);
                }
            }
            $prev = $T;
            $T += $dTloss + $dTlat;
            if ($eutStart !== null && $fe < 1 - $maxA) {
                $teMinTruth = $teMinTruth === null ? $prev : min($teMinTruth, $prev);
            }
            $t += $dt;
        }
        $peak = 0.0;
        $after = false;
        foreach ($curve as [$tt, $TT]) {
            if ($eutStart !== null && $tt >= $eutStart) {
                $after = true;
            }
            if ($after) {
                $peak = max($peak, $TT);
                if ($TT < $peak - 25) {
                    break;
                }
            }
        }

        return ['curve' => $curve, 'truth' => ['tl' => $tl, 'te_min' => $teMinTruth, 'te_max' => $peak, 'eutectic_start_s' => $eutStart]];
    }
}
