<?php

namespace App\Services\Production;

use App\Models\CastingLot;
use App\Models\Heat;
use App\Models\Pour;
use App\Support\PouringRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A cupola keeps pouring while it is sampled, so a failing sample cannot stop the pour; it contains it. The period a failing
 * sample covers runs from the last passing sample of the same kind before it (or the start of the run) to the next passing
 * one after it (or until now). Pours that started in that period are flagged, and their lots are held until a person releases
 * them.
 */
class Containment
{
    public function __construct(private HeatSamples $samples) {}

    /**
     * Pours of the heat that fall in a failing period, each with the sample that put it there.
     *
     * @return Collection<string, array{pour: Pour, sample: array}> keyed by pour id
     */
    public function affected(Heat $heat): Collection
    {
        $timeline = $this->samples->timeline($heat);
        $runStart = $heat->first_tap_at ?? $heat->blast_on_at ?? $heat->actual_start_at ?? $heat->created_at;
        $pours = $heat->pours()->whereNotNull('started_at')->get();
        $found = collect();

        foreach (['THERMAL', 'CHEMISTRY'] as $kind) {
            $ofKind = $timeline->where('kind', $kind)->values();
            foreach ($ofKind->where('result', 'FAIL') as $fail) {
                $from = $ofKind->filter(fn ($s) => $s['result'] === 'PASS' && $s['at']->lt($fail['at']))->last()['at'] ?? $runStart;
                $to = $ofKind->first(fn ($s) => $s['result'] === 'PASS' && $s['at']->gt($fail['at']))['at'] ?? null;
                foreach ($pours as $pour) {
                    $inside = $pour->started_at->gte($from) && ($to === null || $pour->started_at->lte($to));
                    if ($inside && ! $found->has($pour->id)) {
                        $found->put($pour->id, ['pour' => $pour, 'sample' => $fail]);
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Flags the affected pours and, unless the rule says only to flag, holds their lots. Safe to run again.
     *
     * @return array{pours: int, lots: int}
     */
    public function apply(Heat $heat): array
    {
        $holdLots = PouringRules::get('containment') === 'HOLD';
        $newPours = 0;
        $heldLots = 0;

        foreach ($this->affected($heat) as ['pour' => $pour, 'sample' => $sample]) {
            $label = ($sample['kind'] === 'THERMAL' ? 'Thermal analysis' : 'Chemistry').' '.($sample['reference'] ?: '').' at '.$sample['at']->format('d M H:i').' was out of limits';
            if ($pour->containment_note === null) {
                $newPours++;
            }
            $pour->update(['containment_note' => $label.($holdLots ? '; lots held.' : '; flagged only.')]);
            if ($holdLots) {
                $heldLots += $this->holdLots($pour, $label);
            }
        }

        return ['pours' => $newPours, 'lots' => $heldLots];
    }

    private function holdLots(Pour $pour, string $reason): int
    {
        $held = 0;
        $lots = CastingLot::whereIn('pour_mould_assembly_id', $pour->mouldAssemblies()->pluck('id'))
            ->whereNotIn('status', ['ON_HOLD', 'DISPATCHED', 'CLOSED', 'SCRAPPED'])->get();
        foreach ($lots as $lot) {
            $lot->update(['status' => 'ON_HOLD', 'notes' => trim($lot->notes."\n".Carbon::now()->format('d M H:i').' hold: containment, '.$reason)]);
            $held++;
        }

        return $held;
    }

    /** The words for a flash message, or null when nothing was flagged. */
    public function message(array $result): ?string
    {
        if ($result['pours'] === 0 && $result['lots'] === 0) {
            return null;
        }

        return 'Containment: '.$result['pours'].' pour'.($result['pours'] === 1 ? '' : 's').' flagged'.($result['lots'] ? ', '.$result['lots'].' lot'.($result['lots'] === 1 ? '' : 's').' held' : '').'.';
    }
}
