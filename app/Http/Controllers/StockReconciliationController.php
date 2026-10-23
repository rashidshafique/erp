<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockLotCostLayer;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockReconciliationController extends Controller
{
    private const MOVEMENT_TYPES = ['RECEIPT', 'ISSUE', 'TRANSFER'];

    private const TOLERANCE = 0.001;

    public function index(): View
    {
        $materials = Material::query()
            ->where('is_stock_tracked', true)
            ->orderBy('name')
            ->get();

        $rows = $materials->map(
            fn (Material $material) => $this->reconcileMaterial($material)
        );

        $lots = MaterialLot::query()
            ->whereHas(
                'material',
                fn ($query) => $query->where('is_stock_tracked', true)
            )
            ->with('material')
            ->withSum('costLayers as layer_qty', 'remaining_qty')
            ->withSum(
                'costLayers as layer_value',
                DB::raw('remaining_qty * unit_cost')
            )
            ->withCount('movements')
            ->orderBy('material_id')
            ->orderBy('lot_number')
            ->get();

        $lotRows = $lots->map(
            fn (MaterialLot $lot) => $this->reconcileLot($lot)
        );

        return view(
            'stock-reconciliation.index',
            compact('rows', 'lotRows')
        );
    }

    private function reconcileMaterial(Material $material): array
    {
        $lotIds = MaterialLot::query()
            ->where('material_id', $material->id)
            ->pluck('id');

        $allLayers = StockLotCostLayer::query()
            ->whereIn('material_lot_id', $lotIds)
            ->get([
                'id',
                'material_lot_id',
                'stock_movement_id',
                'original_qty',
                'remaining_qty',
                'unit_cost',
            ]);

        $movements = StockMovement::query()
            ->where('material_id', $material->id)
            ->get([
                'id',
                'material_id',
                'material_lot_id',
                'movement_type',
                'quantity',
            ]);

        $receipts = $movements
            ->where('movement_type', 'RECEIPT')
            ->keyBy('id');

        $movementsById = $movements->keyBy('id');

        $receiptLayers = $allLayers->filter(
            fn (StockLotCostLayer $layer) =>
                $receipts->has($layer->stock_movement_id)
        );

        $missingReceiptLayer = $receipts->contains(
            fn (StockMovement $receipt) =>
                ! $receiptLayers->contains(
                    fn (StockLotCostLayer $layer) =>
                        $layer->stock_movement_id === $receipt->id
                )
        );

        $invalidLayer = $this->hasInvalidLayers(
            $allLayers,
            $movementsById
        );

        $receiptLayerQuantityMismatch = $this->hasReceiptLayerQuantityMismatch(
            $receipts,
            $receiptLayers
        );

        $movementSummary = $movements
            ->groupBy('movement_type')
            ->map(fn (Collection $group) => $group->sum('quantity'));

        $receiptsQty = (float) ($movementSummary['RECEIPT'] ?? 0);
        $issuesQty = (float) ($movementSummary['ISSUE'] ?? 0);

        $movementQty = round($receiptsQty - $issuesQty, 3);

        $layerQty = (float) $allLayers->sum('remaining_qty');

        $layerValue = (float) $allLayers->sum(
            fn (StockLotCostLayer $layer) =>
                (float) $layer->remaining_qty * (float) $layer->unit_cost
        );

        $difference = round($movementQty - $layerQty, 3);

        $unsupported = $movements->contains(
            fn (StockMovement $movement) =>
                ! in_array(
                    $movement->movement_type,
                    self::MOVEMENT_TYPES,
                    true
                )
        );

        $status = $this->determineStatus(
            unsupported: $unsupported,
            movementQty: $movementQty,
            invalidLayer: $invalidLayer,
            missingReceiptLayer: $missingReceiptLayer,
            receiptLayerQuantityMismatch: $receiptLayerQuantityMismatch,
            movementCount: $movements->count(),
            layerQty: $layerQty,
            difference: $difference,
        );

        return [
            'material' => $material,
            'lot_count' => $lotIds->count(),
            'movement_count' => $movements->count(),
            'receipts' => $receiptsQty,
            'issues' => $issuesQty,
            'movement_qty' => $movementQty,
            'layer_qty' => $layerQty,
            'layer_value' => $layerValue,
            'difference' => $difference,
            'status' => $status,
        ];
    }

    private function reconcileLot(MaterialLot $lot): array
    {
        $movements = StockMovement::query()
            ->where('material_lot_id', $lot->id)
            ->get([
                'id',
                'material_id',
                'material_lot_id',
                'movement_type',
                'quantity',
            ]);

        $allLayers = StockLotCostLayer::query()
            ->where('material_lot_id', $lot->id)
            ->get([
                'id',
                'material_lot_id',
                'stock_movement_id',
                'original_qty',
                'remaining_qty',
                'unit_cost',
            ]);

        $receipts = $movements
            ->where('movement_type', 'RECEIPT')
            ->keyBy('id');

        $movementsById = $movements->keyBy('id');

        $receiptLayers = $allLayers->filter(
            fn (StockLotCostLayer $layer) =>
                $receipts->has($layer->stock_movement_id)
        );

        $missingReceiptLayer = $receipts->contains(
            fn (StockMovement $receipt) =>
                ! $receiptLayers->contains(
                    fn (StockLotCostLayer $layer) =>
                        $layer->stock_movement_id === $receipt->id
                )
        );

        $invalidLayer = $this->hasInvalidLayers(
            $allLayers,
            $movementsById
        );

        $receiptLayerQuantityMismatch = $this->hasReceiptLayerQuantityMismatch(
            $receipts,
            $receiptLayers
        );

        $receiptsQty = (float) $movements
            ->where('movement_type', 'RECEIPT')
            ->sum('quantity');

        $issuesQty = (float) $movements
            ->where('movement_type', 'ISSUE')
            ->sum('quantity');

        $movementQty = round($receiptsQty - $issuesQty, 3);

        $layerQty = (float) $allLayers->sum('remaining_qty');

        $layerValue = (float) $allLayers->sum(
            fn (StockLotCostLayer $layer) =>
                (float) $layer->remaining_qty * (float) $layer->unit_cost
        );

        $difference = round($movementQty - $layerQty, 3);

        $unsupported = $movements->contains(
            fn (StockMovement $movement) =>
                ! in_array(
                    $movement->movement_type,
                    self::MOVEMENT_TYPES,
                    true
                )
        );

        $status = $this->determineStatus(
            unsupported: $unsupported,
            movementQty: $movementQty,
            invalidLayer: $invalidLayer,
            missingReceiptLayer: $missingReceiptLayer,
            receiptLayerQuantityMismatch: $receiptLayerQuantityMismatch,
            movementCount: $movements->count(),
            layerQty: $layerQty,
            difference: $difference,
        );

        return [
            'lot' => $lot,
            'receipts' => $receiptsQty,
            'issues' => $issuesQty,
            'movement_qty' => $movementQty,
            'layer_qty' => $layerQty,
            'layer_value' => $layerValue,
            'difference' => $difference,
            'movement_count' => $movements->count(),
            'status' => $status,
        ];
    }

    private function hasInvalidLayers(
        Collection $layers,
        Collection $movementsById
    ): bool {
        return $layers->contains(
            function (StockLotCostLayer $layer) use ($movementsById) {
                $movement = $movementsById->get(
                    $layer->stock_movement_id
                );

                return (float) $layer->original_qty < 0
                    || (float) $layer->remaining_qty < 0
                    || (float) $layer->remaining_qty
                        > (float) $layer->original_qty
                    || ! $movement
                    || $movement->movement_type !== 'RECEIPT'
                    || $layer->material_lot_id
                        !== $movement->material_lot_id;
            }
        );
    }

    private function hasReceiptLayerQuantityMismatch(
        Collection $receipts,
        Collection $receiptLayers
    ): bool {
        return $receipts->contains(
            function (StockMovement $receipt) use ($receiptLayers) {
                $originalLayerQty = (float) $receiptLayers
                    ->where('stock_movement_id', $receipt->id)
                    ->sum('original_qty');

                return abs(
                    $originalLayerQty - (float) $receipt->quantity
                ) > self::TOLERANCE;
            }
        );
    }

    private function determineStatus(
        bool $unsupported,
        float $movementQty,
        bool $invalidLayer,
        bool $missingReceiptLayer,
        bool $receiptLayerQuantityMismatch,
        int $movementCount,
        float $layerQty,
        float $difference,
    ): string {
        if ($unsupported) {
            return 'Review Required';
        }

        if ($movementQty < -self::TOLERANCE) {
            return 'Negative Stock';
        }

        if ($invalidLayer) {
            return 'Invalid Cost Layer';
        }

        if ($missingReceiptLayer) {
            return 'Missing Cost Layer';
        }

        if ($receiptLayerQuantityMismatch) {
            return 'Receipt Layer Quantity Mismatch';
        }

        if (
            $movementCount === 0
            && abs($layerQty) <= self::TOLERANCE
        ) {
            return 'No Activity';
        }

        if (abs($difference) > self::TOLERANCE) {
            return 'Quantity Mismatch';
        }

        return 'Reconciled';
    }
}