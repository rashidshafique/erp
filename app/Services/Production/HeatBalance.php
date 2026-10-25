<?php

namespace App\Services\Production;

use App\Models\Heat;

/** Where a heat's metal went: charged, tapped, poured, and what the poured metal made. */
class HeatBalance
{
    /**
     * @return array{
     *     charged_kg: float, tapped_kg: ?float, poured_kg: float, first_iron_kg: float, pours: int, open_pours: int,
     *     lots: int, held_lots: int, net_casting_kg: float, poured_percent_of_charged: ?float, casting_yield_percent: ?float,
     *     unpoured_kg: ?float, melt_rate_kg_per_hour: ?float, warnings: array<int, string>
     * }
     */
    public function for(Heat $heat): array
    {
        $heat->loadMissing('pours.mouldAssemblies.castingLots', 'materialConsumptions');
        $completed = $heat->pours->where('status', 'COMPLETED');
        $poured = (float) $completed->sum('total_poured_weight_kg');
        $charged = $heat->chargedMetalKg();   // metal only, worked out from the recorded charges; coke and flux are not metal
        $tapped = $heat->actual_tapped_weight_kg !== null ? (float) $heat->actual_tapped_weight_kg : null;
        $lots = $completed->flatMap(fn ($pour) => $pour->mouldAssemblies->flatMap->castingLots);
        $net = (float) $lots->sum('net_casting_weight_kg');
        $from = $heat->first_tap_at ?? $heat->actual_start_at;
        $hours = $from ? max(0.1, $from->diffInMinutes($heat->dropped_at ?? now()) / 60) : null;

        $warnings = [];
        if ($tapped !== null && $poured > $tapped + 0.5) {
            $warnings[] = 'More metal was poured ('.\App\Support\Formats::number($poured, 1).' kg) than was tapped ('.\App\Support\Formats::number($tapped, 1).' kg). Check the weights on the pours.';
        }
        if ($charged > 0 && $poured > $charged + 0.5) {
            $warnings[] = 'More metal was poured ('.\App\Support\Formats::number($poured, 1).' kg) than metal was charged ('.\App\Support\Formats::number($charged, 1).' kg).';
        }

        return [
            'charged_kg' => $charged,
            'tapped_kg' => $tapped,
            'poured_kg' => $poured,
            'first_iron_kg' => (float) $completed->where('is_first_iron', true)->sum('total_poured_weight_kg'),
            'pours' => $completed->count(),
            'open_pours' => $heat->pours->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->count(),
            'lots' => $lots->count(),
            'held_lots' => $lots->where('status', 'ON_HOLD')->count(),
            'net_casting_kg' => $net,
            'poured_percent_of_charged' => $charged > 0 ? round($poured / $charged * 100, 1) : null,
            'casting_yield_percent' => $poured > 0 && $net > 0 ? round($net / $poured * 100, 1) : null,
            'unpoured_kg' => $tapped !== null ? round($tapped - $poured, 3) : null,
            'melt_rate_kg_per_hour' => $hours && $poured > 0 ? round($poured / $hours, 0) : null,
            'warnings' => $warnings,
        ];
    }
}
