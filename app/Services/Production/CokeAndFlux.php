<?php

namespace App\Services\Production;

use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Models\Material;

/**
 * The coke and flux a heat used against what its recipe planned for the charges actually put in, the coke ratio (metal per kg of
 * coke) and their cost per tonne of metal melted. Extras, entries outside the recipe's charges, are totalled by their reason, so a
 * recipe that keeps needing the same top-up shows.
 */
class CokeAndFlux
{
    /** Kind => what the summary calls it. */
    public const KINDS = ['COKE' => 'Coke', 'FLUX' => 'Flux'];

    /** A recipe role or material type => the kind it counts as. Limestone is a flux; fuel is coke. */
    private const KIND_OF = ['COKE' => 'COKE', 'FUEL' => 'COKE', 'FLUX' => 'FLUX', 'LIMESTONE' => 'FLUX'];

    /**
     * @return ?array{
     *     kinds: array<string, array{label: string, planned_kg: ?float, actual_kg: float, cost: float}>,
     *     metal_kg: float, coke_ratio: ?float, cost_per_tonne: ?float,
     *     extras: list<array{reason: string, label: string, kind: string, kg: float}>
     * } null when the heat neither planned nor used any
     */
    public function for(Heat $heat): ?array
    {
        $heat->loadMissing('materialConsumptions.material', 'chargeBatch.items.material', 'chargeBatch.items.chargeBatch');
        $items = $heat->chargeBatch?->items ?? collect();
        // A material is coke or flux by its role in the heat's recipe, else by its material type.
        $roles = $items->mapWithKeys(fn ($i) => [$i->material_id => self::KIND_OF[strtoupper((string) $i->component_role)] ?? null])->filter();
        $kindOf = fn ($material) => $material === null ? null : ($roles[$material->id] ?? self::KIND_OF[strtoupper((string) $material->material_type)] ?? null);
        $entries = $heat->materialConsumptions->filter(fn ($e) => $kindOf($e->material) !== null);
        $rounds = $heat->materialConsumptions->pluck('charge_round')->filter(fn ($r) => $r !== null)->unique();

        $kinds = [];
        foreach (self::KINDS as $type => $label) {
            $ofKind = $entries->filter(fn ($e) => $kindOf($e->material) === $type);
            $planItems = $items->filter(fn ($i) => $kindOf($i->material) === $type);
            // Planned for the charges put in: the start-up charge's items once if it went in, each round's items per round.
            $planned = $planItems->isEmpty() ? null : $rounds->sum(fn ($round) => $planItems->where('charge_stage', $round === 0 ? 'BED' : 'ROUND')->sum(fn ($i) => (float) $i->perChargeKg()));
            if ($ofKind->isEmpty() && ! $planned) {
                continue;
            }
            $kinds[$type] = ['label' => $label, 'planned_kg' => $planned, 'actual_kg' => (float) $ofKind->sum('actual_qty_kg'), 'cost' => (float) $ofKind->sum('actual_total_cost')];
        }
        if ($kinds === []) {
            return null;
        }

        // Metal is everything charged that is neither coke nor flux.
        $metal = (float) $heat->materialConsumptions->filter(fn ($e) => $kindOf($e->material) === null && ! in_array($e->material?->material_type, Material::NON_METAL_TYPES, true))->sum('actual_qty_kg');
        $coke = $kinds['COKE']['actual_kg'] ?? 0.0;
        $cost = array_sum(array_column($kinds, 'cost'));
        $extras = $entries->filter(fn ($e) => $e->charge_round === null && $heat->charge_batch_id !== null)
            ->groupBy(fn ($e) => ($e->addition_reason ?? '').'|'.$kindOf($e->material))
            ->map(function ($group) use ($kindOf) {
                $first = $group->first();

                return ['reason' => (string) $first->addition_reason, 'label' => HeatMaterialConsumption::ADDITION_REASONS[$first->addition_reason] ?? 'No reason given',
                    'kind' => self::KINDS[$kindOf($first->material)], 'kg' => (float) $group->sum('actual_qty_kg')];
            })->values()->all();

        return [
            'kinds' => $kinds,
            'metal_kg' => $metal,
            'coke_ratio' => $coke > 0 && $metal > 0 ? round($metal / $coke, 1) : null,
            'cost_per_tonne' => $metal > 0 ? round($cost / ($metal / 1000), 2) : null,
            'extras' => $extras,
        ];
    }
}
