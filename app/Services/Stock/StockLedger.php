<?php

namespace App\Services\Stock;

use App\Models\MaterialLot;
use App\Models\StockLotCostLayer;
use App\Models\StockMovement;
use App\Support\Formats;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The one place stock movements are written. A receipt opens a FIFO cost layer; an issue draws the lot's layers down,
 * oldest first, and costs the movement from them; a transfer only changes where the stock sits. Cost layers are opened
 * by receipts alone, so a stock gain is recorded as a receipt and a loss as an issue, told apart by `source_type`.
 *
 * Every method expects to run inside the caller's transaction, with the lot locked where concurrent posting matters.
 */
class StockLedger
{
    /**
     * Receives stock into a lot and opens its cost layer.
     *
     * @param  array{movement_number?: string, source_type?: string|null, source_id?: string|null, receipt_lot_id?: string|null, recorded_by?: string|null, reason?: string|null, to_location_id?: string|null, occurred_at?: CarbonInterface|null}  $details
     */
    public function receive(MaterialLot $lot, float $quantity, float $unitCost, string $uom, array $details = []): StockMovement
    {
        $at = $details['occurred_at'] ?? now();

        $movement = StockMovement::create([
            'movement_number' => $details['movement_number'] ?? $this->number('RCV'),
            'movement_type' => 'RECEIPT',
            'material_id' => $lot->material_id,
            'material_lot_id' => $lot->id,
            'to_location_id' => $details['to_location_id'] ?? null,
            'quantity' => $quantity,
            'uom' => $uom,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 4),
            'source_type' => $details['source_type'] ?? null,
            'source_id' => $details['source_id'] ?? null,
            'receipt_lot_id' => $details['receipt_lot_id'] ?? null,
            'occurred_at' => $at,
            'recorded_by' => $details['recorded_by'] ?? null,
            'reason' => $details['reason'] ?? null,
        ]);

        StockLotCostLayer::create([
            'material_lot_id' => $lot->id,
            'stock_movement_id' => $movement->id,
            'original_qty' => $quantity,
            'remaining_qty' => $quantity,
            'unit_cost' => $unitCost,
            'currency_code' => 'PKR',
            'received_at' => $at,
        ]);

        return $movement;
    }

    /**
     * Issues stock from a lot, drawing its cost layers down oldest first. The movement carries the average cost of what it drew.
     *
     * @param  array{movement_number?: string, source_type?: string|null, source_id?: string|null, recorded_by?: string|null, reason?: string|null, from_location_id?: string|null, occurred_at?: CarbonInterface|null}  $details
     *
     * @throws InsufficientStockException
     */
    public function issue(MaterialLot $lot, float $quantity, string $uom, array $details = []): StockMovement
    {
        $fromLocation = $details['from_location_id'] ?? null;
        if ($fromLocation !== null) {
            $this->assertHeldAt($lot, $fromLocation, $quantity);
        }

        $layers = StockLotCostLayer::query()
            ->where('material_lot_id', $lot->id)
            ->where('currency_code', 'PKR')
            ->where('remaining_qty', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = $layers->sum(fn ($layer) => (float) $layer->remaining_qty);
        if ($available + 0.000001 < $quantity) {
            throw new InsufficientStockException("Insufficient posted stock in lot {$lot->lot_number}. Available: "
                .Formats::number($available, 3).' kg; required: '.Formats::number($quantity, 3).' kg.');
        }

        $remainingToIssue = $quantity;
        $issueCost = 0.0;
        foreach ($layers as $layer) {
            if ($remainingToIssue <= 0.000001) {
                break;
            }
            $layerRemaining = (float) $layer->remaining_qty;
            $taken = min($layerRemaining, $remainingToIssue);
            $issueCost += round($taken * (float) $layer->unit_cost, 4);
            $layer->remaining_qty = round($layerRemaining - $taken, 3);
            $layer->save();
            $remainingToIssue = round($remainingToIssue - $taken, 3);
        }
        if ($remainingToIssue > 0.000001) {
            throw new InsufficientStockException("Could not fully allocate the issue from lot {$lot->lot_number} to its cost layers.");
        }
        $issueCost = round($issueCost, 4);

        return StockMovement::create([
            'movement_number' => $details['movement_number'] ?? $this->number('ISS'),
            'movement_type' => 'ISSUE',
            'material_id' => $lot->material_id,
            'material_lot_id' => $lot->id,
            'from_location_id' => $fromLocation,
            'quantity' => $quantity,
            'uom' => $uom,
            'unit_cost' => round($issueCost / $quantity, 4),
            'total_cost' => $issueCost,
            'source_type' => $details['source_type'] ?? null,
            'source_id' => $details['source_id'] ?? null,
            'occurred_at' => $details['occurred_at'] ?? now(),
            'recorded_by' => $details['recorded_by'] ?? null,
            'reason' => $details['reason'] ?? null,
        ]);
    }

    /**
     * Moves stock of a lot from one place to another. A null location is the stock not yet put anywhere. Quantity and cost do not change.
     *
     * @param  array{movement_number?: string, recorded_by?: string|null, reason?: string|null, occurred_at?: CarbonInterface|null}  $details
     *
     * @throws InsufficientStockException
     */
    public function transfer(MaterialLot $lot, float $quantity, string $uom, ?string $fromLocationId, ?string $toLocationId, array $details = []): StockMovement
    {
        $this->assertHeldAt($lot, $fromLocationId, $quantity);

        $layerCost = $this->averageLayerCost($lot);

        return StockMovement::create([
            'movement_number' => $details['movement_number'] ?? $this->number('TRF'),
            'movement_type' => 'TRANSFER',
            'material_id' => $lot->material_id,
            'material_lot_id' => $lot->id,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'quantity' => $quantity,
            'uom' => $uom,
            'unit_cost' => $layerCost,
            'total_cost' => round($quantity * $layerCost, 4),
            'source_type' => 'TRANSFER',
            'occurred_at' => $details['occurred_at'] ?? now(),
            'recorded_by' => $details['recorded_by'] ?? null,
            'reason' => $details['reason'] ?? null,
        ]);
    }

    /** What the lot has left in its cost layers. */
    public function onHand(MaterialLot $lot): float
    {
        return round((float) StockLotCostLayer::where('material_lot_id', $lot->id)->sum('remaining_qty'), 3);
    }

    /**
     * What the lot holds at each location, with the stock not put anywhere under the '' key. Only non-zero places are listed.
     *
     * @return array<string, float> Keyed by location id, or '' for stock with no location.
     */
    public function heldByLocation(MaterialLot $lot): array
    {
        return $this->heldByLocationForLots([$lot->id])[$lot->id] ?? [];
    }

    /**
     * The same for many lots at once, in two queries.
     *
     * @param  list<string>  $lotIds
     * @return array<string, array<string, float>> Lot id => location id ('' for none) => quantity.
     */
    public function heldByLocationForLots(array $lotIds): array
    {
        $held = array_fill_keys($lotIds, []);

        $movements = StockMovement::whereIn('material_lot_id', $lotIds)->get(['material_lot_id', 'movement_type', 'quantity', 'from_location_id', 'to_location_id']);
        foreach ($movements as $movement) {
            $quantity = (float) $movement->quantity;
            if ($movement->to_location_id && in_array($movement->movement_type, ['RECEIPT', 'TRANSFER'], true)) {
                $held[$movement->material_lot_id][$movement->to_location_id] = ($held[$movement->material_lot_id][$movement->to_location_id] ?? 0) + $quantity;
            }
            if ($movement->from_location_id && in_array($movement->movement_type, ['ISSUE', 'TRANSFER'], true)) {
                $held[$movement->material_lot_id][$movement->from_location_id] = ($held[$movement->material_lot_id][$movement->from_location_id] ?? 0) - $quantity;
            }
        }

        $onHand = StockLotCostLayer::whereIn('material_lot_id', $lotIds)->groupBy('material_lot_id')
            ->selectRaw('material_lot_id, sum(remaining_qty) as on_hand')->pluck('on_hand', 'material_lot_id');

        foreach ($held as $lotId => $places) {
            $places = array_map(fn ($quantity) => round($quantity, 3), $places);
            $places[''] = round((float) ($onHand[$lotId] ?? 0) - array_sum($places), 3);
            $held[$lotId] = array_filter($places, fn ($quantity) => abs($quantity) > 0.0005);
        }

        return $held;
    }

    /** @throws InsufficientStockException */
    private function assertHeldAt(MaterialLot $lot, ?string $locationId, float $quantity): void
    {
        $held = $this->heldByLocation($lot)[$locationId ?? ''] ?? 0.0;
        if ($held + 0.000001 < $quantity) {
            $where = $locationId === null ? 'with no location' : 'at that location';
            throw new InsufficientStockException("Lot {$lot->lot_number} holds only ".Formats::number($held, 3)." kg {$where}; ".Formats::number($quantity, 3).' kg asked for.');
        }
    }

    private function averageLayerCost(MaterialLot $lot): float
    {
        $layers = StockLotCostLayer::where('material_lot_id', $lot->id)->where('remaining_qty', '>', 0)->get();
        $quantity = (float) $layers->sum('remaining_qty');

        return $quantity > 0 ? round($layers->sum(fn ($l) => (float) $l->remaining_qty * (float) $l->unit_cost) / $quantity, 4) : (float) $lot->unit_cost;
    }

    /** A unique movement number for a movement nobody numbers by hand. */
    public function number(string $prefix): string
    {
        return $prefix.'-'.strtoupper((string) Str::ulid());
    }
}
