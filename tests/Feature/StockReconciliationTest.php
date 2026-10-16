<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockLotCostLayer;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function makeMaterial(string $suffix): Material
    {
        return Material::create([
            'code' => "TEST-STOCK-{$suffix}",
            'name' => "Test Stock {$suffix}",
            'material_type' => 'RAW_MATERIAL',
            'base_uom' => 'kg',
            'status' => 'ACTIVE',
            'is_stock_tracked' => true,
            'requires_lot_tracking' => true,
        ]);
    }

    private function makeLot(
        Material $material,
        string $suffix
    ): MaterialLot {
        return MaterialLot::create([
            'lot_number' => "TEST-LOT-{$suffix}",
            'material_id' => $material->id,
            'status' => 'ACCEPTED',
            'received_qty' => 10,
            'accepted_qty' => 10,
            'unit_cost' => 100,
            'currency_code' => 'PKR',
        ]);
    }

    private function makeMovement(
        Material $material,
        MaterialLot $lot,
        string $suffix,
        string $type,
        float $quantity
    ): StockMovement {
        return StockMovement::create([
            'movement_number' => "TEST-MOVEMENT-{$suffix}",
            'movement_type' => $type,
            'material_id' => $material->id,
            'material_lot_id' => $lot->id,
            'quantity' => $quantity,
            'uom' => 'kg',
            'unit_cost' => 100,
            'total_cost' => $quantity * 100,
            'occurred_at' => now(),
            'source_type' => 'TEST',
            'reason' => 'Automated reconciliation test',
        ]);
    }

    private function makeCostLayer(
        MaterialLot $lot,
        StockMovement $movement,
        string $suffix,
        float $quantity,
        float $unitCost = 100
    ): StockLotCostLayer {
        return StockLotCostLayer::create([
            'material_lot_id' => $lot->id,
            'stock_movement_id' => $movement->id,
            'original_qty' => $quantity,
            'remaining_qty' => $quantity,
            'unit_cost' => $unitCost,
            'currency_code' => 'PKR',
            'received_at' => now(),
        ]);
    }

    public function test_multiple_receipts_and_partial_issue_reconcile(): void
    {
        $material = $this->makeMaterial('PARTIAL');
        $lot = $this->makeLot($material, 'PARTIAL');

        $receipt1 = $this->makeMovement(
            $material, $lot, 'PARTIAL-R1', 'RECEIPT', 10
        );

        $receipt2 = $this->makeMovement(
            $material, $lot, 'PARTIAL-R2', 'RECEIPT', 5
        );

        $this->makeMovement(
            $material, $lot, 'PARTIAL-I1', 'ISSUE', 4
        );

        $layer1 = $this->makeCostLayer(
            $lot, $receipt1, 'PARTIAL-L1', 10
        );

        $this->makeCostLayer(
            $lot, $receipt2, 'PARTIAL-L2', 5
        );

        // Four kilograms consumed from the first receipt's cost layer.
        $layer1->update(['remaining_qty' => 6]);

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Reconciled');
    }

    public function test_cost_layer_value_uses_each_layers_unit_cost(): void
    {
        $material = $this->makeMaterial('COSTS');
        $lot = $this->makeLot($material, 'COSTS');

        $receipt1 = $this->makeMovement(
            $material, $lot, 'COSTS-R1', 'RECEIPT', 5
        );

        $receipt2 = $this->makeMovement(
            $material, $lot, 'COSTS-R2', 'RECEIPT', 5
        );

        $this->makeCostLayer(
            $lot, $receipt1, 'COSTS-L1', 5, 100
        );

        $this->makeCostLayer(
            $lot, $receipt2, 'COSTS-L2', 5, 150
        );

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Reconciled');
    }

    public function test_missing_layer_on_one_of_multiple_receipts_is_flagged(): void
    {
        $material = $this->makeMaterial('PARTIAL-LAYERS');
        $lot = $this->makeLot($material, 'PARTIAL-LAYERS');

        $receipt1 = $this->makeMovement(
            $material,
            $lot,
            'PARTIAL-LAYERS-R1',
            'RECEIPT',
            5
        );

        $this->makeMovement(
            $material,
            $lot,
            'PARTIAL-LAYERS-R2',
            'RECEIPT',
            5
        );

        $this->makeCostLayer(
            $lot,
            $receipt1,
            'PARTIAL-LAYERS-L1',
            5
        );

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Missing Cost Layer');
    }

    public function test_reconciliation_page_loads_and_reports_no_activity(): void
    {
        $material = $this->makeMaterial('EMPTY');

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee($material->name);
        $response->assertSee('No activity');
    }

    public function test_receipt_with_matching_cost_layer_is_reconciled(): void
    {
        $material = $this->makeMaterial('MATCH');
        $lot = $this->makeLot($material, 'MATCH');

        $receipt = $this->makeMovement(
            $material,
            $lot,
            'MATCH',
            'RECEIPT',
            10
        );

        $this->makeCostLayer($lot, $receipt, 'MATCH', 10);

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Reconciled');
    }

    public function test_receipt_without_a_cost_layer_is_flagged(): void
    {
        $material = $this->makeMaterial('MISSING-LAYER');
        $lot = $this->makeLot($material, 'MISSING-LAYER');

        $this->makeMovement(
            $material,
            $lot,
            'MISSING-LAYER',
            'RECEIPT',
            10
        );

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Missing Cost Layer');
    }

    public function test_issue_exceeding_receipts_is_flagged_as_negative_stock(): void
    {
        $material = $this->makeMaterial('NEGATIVE');
        $lot = $this->makeLot($material, 'NEGATIVE');

        $this->makeMovement(
            $material,
            $lot,
            'NEGATIVE-ISSUE',
            'ISSUE',
            5
        );

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Negative Stock');
    }

    public function test_unsupported_movement_type_requires_review(): void
    {
        $material = $this->makeMaterial('UNSUPPORTED');
        $lot = $this->makeLot($material, 'UNSUPPORTED');

        $this->makeMovement(
            $material,
            $lot,
            'UNSUPPORTED',
            'TRANSFER',
            2
        );

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Review Required');
    }

    public function test_movement_and_cost_layer_quantity_mismatch_is_flagged(): void
    {
        $material = $this->makeMaterial('MISMATCH');
        $lot = $this->makeLot($material, 'MISMATCH');

        $receipt = $this->makeMovement(
            $material,
            $lot,
            'MISMATCH',
            'RECEIPT',
            10
        );

        // The layer has less original quantity than the receipt.
        $this->makeCostLayer($lot, $receipt, 'MISMATCH', 7);

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Receipt Layer Quantity Mismatch');
    }

    public function test_receipt_with_multiple_layers_checks_original_quantity_total(): void
    {
        $material = $this->makeMaterial('LAYER-TOTAL');
        $lot = $this->makeLot($material, 'LAYER-TOTAL');

        $receipt = $this->makeMovement(
            $material,
            $lot,
            'LAYER-TOTAL-R1',
            'RECEIPT',
            10
        );

        $this->makeCostLayer(
            $lot, $receipt, 'LAYER-TOTAL-L1', 4
        );

        $this->makeCostLayer(
            $lot, $receipt, 'LAYER-TOTAL-L2', 4
        );

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Receipt Layer Quantity Mismatch');
    }

    public function test_cost_layer_with_remaining_quantity_above_original_is_flagged(): void
    {
        $material = $this->makeMaterial('INVALID-BALANCE');
        $lot = $this->makeLot($material, 'INVALID-BALANCE');

        $receipt = $this->makeMovement(
            $material,
            $lot,
            'INVALID-BALANCE-R1',
            'RECEIPT',
            10
        );

        $layer = $this->makeCostLayer(
            $lot,
            $receipt,
            'INVALID-BALANCE-L1',
            10
        );

        $layer->update(['remaining_qty' => 12]);

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Invalid Cost Layer');
        
    }

    public function test_cost_layer_linked_to_non_receipt_movement_is_flagged(): void
    {
        $material = $this->makeMaterial('ORPHAN-LAYER');
        $lot = $this->makeLot($material, 'ORPHAN-LAYER');

        $receipt = $this->makeMovement(
            $material,
            $lot,
            'ORPHAN-LAYER-R1',
            'RECEIPT',
            10
        );

        $this->makeCostLayer(
            $lot,
            $receipt,
            'ORPHAN-LAYER-L1',
            10
        );

        $issue = $this->makeMovement(
            $material,
            $lot,
            'ORPHAN-LAYER-I1',
            'ISSUE',
            2
        );

        // Deliberately link the cost layer to an ISSUE instead of a RECEIPT.
        $layer = $lot->costLayers()->firstOrFail();
        $layer->update(['stock_movement_id' => $issue->id]);

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Invalid Cost Layer');
    }

    public function test_partially_consumed_cost_layer_remains_valid(): void
    {
        $material = $this->makeMaterial('PARTIAL-CONSUMPTION');
        $lot = $this->makeLot($material, 'PARTIAL-CONSUMPTION');

        $receipt = $this->makeMovement(
            $material,
            $lot,
            'PARTIAL-CONSUMPTION-R1',
            'RECEIPT',
            10
        );

        $this->makeMovement(
            $material,
            $lot,
            'PARTIAL-CONSUMPTION-I1',
            'ISSUE',
            4
        );

        $layer = $this->makeCostLayer(
            $lot,
            $receipt,
            'PARTIAL-CONSUMPTION-L1',
            10
        );

        $layer->update(['remaining_qty' => 6]);

        $response = $this->get(route('stock-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Reconciled');
    }
}