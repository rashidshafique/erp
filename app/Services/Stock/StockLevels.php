<?php

namespace App\Services\Stock;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockLotCostLayer;
use Illuminate\Support\Collection;

/** How much of each material is on hand, and which materials have fallen to their minimum. */
class StockLevels
{
    /**
     * What each material has left in its cost layers.
     *
     * @param  list<string>|null  $materialIds  Limit to these materials; all when null.
     * @return array<string, float> Material id => quantity; materials with no stock are absent.
     */
    public function onHand(?array $materialIds = null): array
    {
        return StockLotCostLayer::query()
            ->join('material_lots', 'material_lots.id', '=', 'stock_lot_cost_layers.material_lot_id')
            ->when($materialIds !== null, fn ($q) => $q->whereIn('material_lots.material_id', $materialIds))
            ->groupBy('material_lots.material_id')
            ->selectRaw('material_lots.material_id as material_id, sum(stock_lot_cost_layers.remaining_qty) as on_hand')
            ->pluck('on_hand', 'material_id')
            ->map(fn ($quantity) => round((float) $quantity, 3))
            ->all();
    }

    /**
     * What is held at each location, all materials together, in kg. Stock not put anywhere is left out.
     *
     * @return array<string, float> Location id => quantity; empty places are absent.
     */
    public function onHandByLocation(): array
    {
        $lotIds = MaterialLot::whereHas('costLayers', fn ($q) => $q->where('remaining_qty', '>', 0))->pluck('id')->all();
        $totals = [];
        foreach (app(StockLedger::class)->heldByLocationForLots($lotIds) as $places) {
            foreach ($places as $locationId => $quantity) {
                if ($locationId !== '') {
                    $totals[$locationId] = round(($totals[$locationId] ?? 0) + $quantity, 3);
                }
            }
        }

        return $totals;
    }

    /** Whether the material is watched: tracked, active, and given a minimum above zero. */
    public function isWatched(Material $material): bool
    {
        return $material->is_stock_tracked && $material->status === 'ACTIVE' && $material->deleted_at === null && (float) $material->minimum_stock_qty > 0;
    }

    public function isAtOrBelowMinimum(Material $material, float $onHand): bool
    {
        return $this->isWatched($material) && $onHand <= (float) $material->minimum_stock_qty + 0.0005;
    }

    /** The quantity to order: the material's reorder quantity when set, otherwise enough to get back to its minimum. */
    public function suggestedOrder(Material $material, float $onHand): float
    {
        $backToMinimum = max(0.0, (float) $material->minimum_stock_qty - $onHand);

        return round(max((float) $material->reorder_qty, $backToMinimum), 3);
    }

    /**
     * Every watched material at or below its minimum, the emptiest first.
     *
     * @return Collection<int, array{material: Material, on_hand: float, minimum: float, shortfall: float, suggested: float, out: bool}>
     */
    public function needingReorder(): Collection
    {
        $onHand = $this->onHand();

        return Material::query()
            ->where('is_stock_tracked', true)->where('status', 'ACTIVE')->where('minimum_stock_qty', '>', 0)
            ->orderBy('name')->get()
            ->map(function (Material $material) use ($onHand) {
                $held = $onHand[$material->id] ?? 0.0;

                return ['material' => $material, 'on_hand' => $held, 'minimum' => (float) $material->minimum_stock_qty,
                    'shortfall' => round((float) $material->minimum_stock_qty - $held, 3), 'suggested' => $this->suggestedOrder($material, $held), 'out' => $held <= 0.0005];
            })
            ->filter(fn (array $row) => $this->isAtOrBelowMinimum($row['material'], $row['on_hand']))
            ->sortBy(fn (array $row) => [$row['out'] ? 0 : 1, $row['on_hand'] / max((float) $row['minimum'], 0.001)])
            ->values();
    }
}
