<?php

namespace App\Domains\Enquiry\Services;

/**
 * Cost build-up for one casting. Every step is kept so the sales team can see where the price comes from.
 *   metal      poured kg x melt rate of the iron family
 *   conversion moulding + cores + fettling + heat treatment + testing + packing
 *   overhead   % of conversion
 *   scrap      cost is divided by (1 - scrap %) because scrapped castings are paid for by the good ones
 *   tooling    spread per casting when amortised
 *   price      cost / (1 - margin %), margin on price
 */
class QuoteCalculator
{
    /**
     * @param  array  $in  weight_kg, poured_kg, family, cores_count, heat_treatment, ndt, machining_cost, other_cost,
     *                     scrap_pct, overhead_pct, margin_pct, tooling_cost, price_override (nullable)
     * @param  array  $ctx  tooling_mode, tooling_amortise_qty
     */
    public function line(array $in, array $rates, array $ctx = []): array
    {
        $w = (float) $in['weight_kg'];
        $p = (float) $in['poured_kg'];
        $melt = $in['family'] === 'DUCTILE' ? $rates['melt_ductile_per_kg'] : $rates['melt_grey_per_kg'];

        $metal = $p * $melt;
        $moulding = $p * $rates['moulding_per_kg'];
        $cores = (int) $in['cores_count'] * $rates['core_each'];
        $fettling = $w * $rates['fettling_per_kg'];
        $ht = ! empty($in['heat_treatment']) ? $w * $rates['heat_treatment_per_kg'] : 0.0;
        $test = $rates['testing_each'] + (! empty($in['ndt']) ? $rates['ndt_each'] : 0.0);
        $packing = $rates['packing_each'];
        $conversion = $moulding + $cores + $fettling + $ht + $test + $packing;
        $overhead = $conversion * ((float) $in['overhead_pct']) / 100;
        $machining = (float) ($in['machining_cost'] ?? 0);
        $other = (float) ($in['other_cost'] ?? 0);
        $direct = $metal + $conversion + $overhead + $machining + $other;

        $scrap = min(60.0, max(0.0, (float) $in['scrap_pct']));
        $withScrap = $direct / (1 - $scrap / 100);
        $scrapCost = $withScrap - $direct;

        $mode = $ctx['tooling_mode'] ?? 'NONE';
        $qty = (int) ($ctx['tooling_amortise_qty'] ?? 0);
        $toolCost = (float) ($in['tooling_cost'] ?? 0);
        $toolCostPer = ($mode === 'AMORTISED' && $qty > 0) ? $toolCost / $qty : 0.0;
        $toolPricePer = $toolCostPer * (1 + $rates['tooling_markup_pct'] / 100);

        $margin = min(90.0, max(0.0, (float) $in['margin_pct']));
        $unitCost = $withScrap + $toolCostPer;
        $calc = $withScrap / (1 - $margin / 100) + $toolPricePer;
        $override = isset($in['price_override']) && $in['price_override'] !== '' && $in['price_override'] !== null ? (float) $in['price_override'] : null;
        $price = $override ?? $calc;
        $eff = $price > 0 ? ($price - $unitCost) / $price * 100 : 0.0;

        $r = fn ($v) => round($v, 2);

        return [
            'unit_cost' => $r($unitCost), 'unit_price' => $r($price), 'calculated_price' => $r($calc), 'effective_margin_pct' => round($eff, 2),
            'breakdown' => [
                'metal' => $r($metal), 'moulding' => $r($moulding), 'cores' => $r($cores), 'fettling' => $r($fettling), 'heat_treatment' => $r($ht),
                'testing' => $r($test), 'packing' => $r($packing), 'overhead' => $r($overhead), 'machining' => $r($machining), 'other' => $r($other),
                'scrap' => $r($scrapCost), 'tooling' => $r($toolCostPer), 'tooling_price' => $r($toolPricePer),
                'melt_rate' => $melt, 'poured_kg' => round($p, 3), 'weight_kg' => round($w, 3),
            ],
        ];
    }
}
