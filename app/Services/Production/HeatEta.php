<?php

namespace App\Services\Production;

use App\Models\Heat;
use App\Support\Formats;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * When a running heat should finish: the metal still to melt (the heat's target less what has been tapped or poured) at a
 * melting rate. The rate is measured on this heat when there is enough to go on, else the furnace's recorded average over
 * its recent heats, else its rated melting speed.
 */
class HeatEta
{
    /** Minutes a heat must have run, and kilograms it must have given, before its own rate is trusted. */
    private const LIVE_MIN_MINUTES = 10;

    private const LIVE_MIN_KG = 50;

    /** Closed heats of the furnace averaged for its recorded rate. */
    private const RECORDED_HEATS = 10;

    public function __construct(private HeatBalance $balance) {}

    /**
     * @return array{finish_at: CarbonInterface, remaining_kg: float, rate_kg_per_hour: float, source: string, label: string}|null
     *         null when the heat is not running or has no target metal or no rate to work with.
     */
    public function for(Heat $heat): ?array
    {
        $run = $heat->furnaceRun();
        $target = (float) $heat->target_metal_weight_kg;
        if (! $run['running'] || $target <= 0) {
            return null;
        }
        $done = $this->metalSoFar($heat);
        $measured = $this->rate($heat);
        if (! $measured) {
            return null;
        }
        ['rate' => $rate, 'source' => $source] = $measured;
        $label = ['live' => 'live rate', 'recorded' => 'recorded average', 'rated' => 'rated speed'][$source].' '.Formats::number($rate).' kg/h';

        if ($heat->isCupola()) {
            return $this->cupolaFinish($heat, $target, $rate, $source, $label);
        }

        // Without a live rate nothing has been measured yet, so the whole target is timed from the start of the run.
        $remaining = max(0.0, $target - $done);
        $finish = $source === 'live'
            ? now()->addMinutes((int) ceil($remaining / $rate * 60))
            : $run['start']->copy()->addMinutes((int) ceil($target / $rate * 60));

        return [
            'finish_at' => $finish,
            'remaining_kg' => round($remaining, 1),
            'rate_kg_per_hour' => round($rate),
            'source' => $source,
            'label' => $label,
        ];
    }

    /**
     * A cupola melts what it is charged, so what is left is the target less the metal charged so far, melted at the rate, plus the
     * time the charge still in the stack needs. With every charge in, the finish is that long after the last one went in.
     *
     * @return array{finish_at: CarbonInterface, remaining_kg: float, rate_kg_per_hour: float, source: string, label: string}
     */
    private function cupolaFinish(Heat $heat, float $target, float $rate, string $source, string $label): array
    {
        $remaining = max(0.0, $target - $heat->chargedMetalKg());
        $perCharge = (float) $heat->chargeBatch?->metalPerChargeKg();
        $lagMinutes = $perCharge > 0 ? $perCharge / $rate * 60 : 0.0;
        if ($remaining > 0) {
            $finish = now()->addMinutes((int) ceil($remaining / $rate * 60 + $lagMinutes));
        } else {
            $lastCharge = $heat->materialConsumptions()->where('charge_round', '>', 0)->max('consumed_at');
            $afterLast = $lastCharge !== null ? Carbon::parse($lastCharge)->addMinutes((int) ceil($lagMinutes)) : null;
            $finish = $afterLast?->isFuture() ? $afterLast : now();
        }

        return ['finish_at' => $finish, 'remaining_kg' => round($remaining, 1), 'rate_kg_per_hour' => round($rate), 'source' => $source, 'label' => $label];
    }

    /**
     * The melting rate to plan with, in kg per hour, and where it comes from: measured on this heat once it has run long enough
     * and given enough metal, else the furnace's recorded average over its recent heats, else its rated melting speed.
     *
     * @return array{rate: float, source: string}|null null when the furnace has no rate on record at all.
     */
    public function rate(Heat $heat): ?array
    {
        $heat->loadMissing('furnace');
        $run = $heat->furnaceRun();
        $done = $this->metalSoFar($heat);
        $minutes = $run['start'] ? $run['start']->diffInMinutes($run['end'] ?? now(), true) : 0;

        [$rate, $source] = match (true) {
            $run['running'] && $minutes >= self::LIVE_MIN_MINUTES && $done >= self::LIVE_MIN_KG => [$done / ($minutes / 60), 'live'],
            ($recorded = $this->recordedRate($heat)) !== null => [$recorded, 'recorded'],
            (float) $heat->furnace?->nominal_melting_rate_kg_per_hour > 0 => [(float) $heat->furnace->nominal_melting_rate_kg_per_hour, 'rated'],
            default => [null, null],
        };

        return $rate ? ['rate' => (float) $rate, 'source' => $source] : null;
    }

    /** Metal this heat has given so far: the tapped weight when recorded, else what has been poured. */
    private function metalSoFar(Heat $heat): float
    {
        if ($heat->actual_tapped_weight_kg !== null) {
            return (float) $heat->actual_tapped_weight_kg;
        }

        return (float) $this->balance->for($heat)['poured_kg'];
    }

    /** The furnace's average melting rate over its last closed heats that recorded a tapped weight and a run time. */
    private function recordedRate(Heat $heat): ?float
    {
        if (! $heat->furnace_id) {
            return null;
        }
        $kg = 0.0;
        $hours = 0.0;
        $past = Heat::where('furnace_id', $heat->furnace_id)->where('id', '!=', $heat->id)->whereIn('status', ['CLOSED', 'TAPPED', 'COMPLETED'])
            ->whereNotNull('actual_tapped_weight_kg')->orderByDesc('actual_start_at')->limit(self::RECORDED_HEATS)->get();
        foreach ($past as $h) {
            $minutes = $h->furnaceRunMinutes();
            if ($minutes && $minutes > 0) {
                $kg += (float) $h->actual_tapped_weight_kg;
                $hours += $minutes / 60;
            }
        }

        return $hours > 0 && $kg > 0 ? $kg / $hours : null;
    }
}
