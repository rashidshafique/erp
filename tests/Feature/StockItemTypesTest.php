<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockMovement;
use App\Services\Stock\InventoryClass;
use App\Services\Stock\StockItemTypes;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockLot;
use App\Services\Stock\StockMovementTypes;
use App\Services\Stock\Stockable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class StockItemTypesTest extends TestCase
{
    use RefreshDatabase;

    private function lot(): MaterialLot
    {
        $material = Material::create(['code' => 'M-'.uniqid(), 'name' => 'Ferro silicon', 'material_type' => 'ALLOY', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => true, 'standard_cost_per_uom' => 250]);

        return MaterialLot::create(['lot_number' => 'L-'.uniqid(), 'material_id' => $material->id, 'status' => 'ACCEPTED', 'received_qty' => 10, 'accepted_qty' => 10, 'unit_cost' => 100, 'currency_code' => 'PKR']);
    }

    public function test_a_material_is_a_registered_stockable_item(): void
    {
        $lot = $this->lot();
        $types = app(StockItemTypes::class);

        $this->assertSame(['material'], $types->keys());
        $this->assertSame(Material::class, $types->modelFor('material'));
        $item = $types->find('material', $lot->material_id);
        $this->assertInstanceOf(Stockable::class, $item);
        $this->assertSame('kg', $item->stockUom());
        $this->assertSame(250.0, $item->standardCost());
        $this->assertTrue($item->isLotTracked());
        $this->assertSame($lot->material->name, $lot->stockItem()->stockName());
    }

    public function test_an_item_type_must_implement_stockable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(StockItemTypes::class)->register('product', \App\Models\Product::class);
    }

    public function test_the_ledger_records_the_item_on_every_movement(): void
    {
        $lot = $this->lot();
        $ledger = app(StockLedger::class);

        $ledger->receive($lot, 10, 100, 'kg');
        $ledger->issue($lot, 4, 'kg');
        $ledger->transfer($lot, 1, 'kg', null, null);

        $this->assertSame(3, StockMovement::where('item_type', 'material')->where('item_id', $lot->material_id)->count());
        $this->assertSame($lot->material_id, StockMovement::first()->item()->stockItemId());
    }

    public function test_a_movement_written_with_only_a_material_and_lot_gets_the_item_columns(): void
    {
        $lot = $this->lot();

        $movement = StockMovement::create(['movement_number' => 'OLD-1', 'movement_type' => 'RECEIPT', 'material_id' => $lot->material_id, 'material_lot_id' => $lot->id,
            'quantity' => 1, 'uom' => 'kg', 'occurred_at' => now()]);

        $this->assertSame('material', $movement->fresh()->item_type);
        $this->assertSame($lot->material_id, $movement->fresh()->item_id);
        $this->assertSame('RAW_MATERIAL', $movement->fresh()->inventory_class);
    }

    public function test_a_movement_is_reported_under_its_items_class_unless_posted_under_another(): void
    {
        $lot = $this->lot();
        $ledger = app(StockLedger::class);

        $ledger->receive($lot, 10, 100, 'kg');
        $ledger->receive($lot, 5, 100, 'kg', ['inventory_class' => InventoryClass::StockInTrade]);

        $this->assertSame(['RAW_MATERIAL', 'STOCK_IN_TRADE'], StockMovement::orderBy('quantity', 'desc')->pluck('inventory_class')->all());
    }

    public function test_the_database_keeps_a_material_movement_in_step_with_its_item(): void
    {
        $lot = $this->lot();
        $other = $this->lot();

        $this->expectException(QueryException::class);
        StockMovement::create(['movement_number' => 'BAD-1', 'movement_type' => 'RECEIPT', 'item_type' => 'material', 'item_id' => $other->material_id,
            'material_id' => $lot->material_id, 'material_lot_id' => $lot->id, 'quantity' => 1, 'uom' => 'kg', 'occurred_at' => now()]);
    }

    public function test_a_lot_of_an_item_without_cost_layers_is_refused(): void
    {
        $item = new class implements Stockable
        {
            public function stockItemType(): string { return 'product'; }
            public function stockItemId(): string { return '00000000-0000-0000-0000-000000000001'; }
            public function stockCode(): string { return 'P'; }
            public function stockName(): string { return 'Casting'; }
            public function stockUom(): string { return 'nos'; }
            public function isStockTracked(): bool { return true; }
            public function isLotTracked(): bool { return false; }
            public function standardCost(): ?float { return null; }
            public function inventoryClass(): InventoryClass { return InventoryClass::FinishedGoods; }
        };
        $lot = new class($item) implements StockLot
        {
            public function __construct(private Stockable $item) {}
            public function stockItem(): Stockable { return $this->item; }
            public function stockLotId(): string { return '00000000-0000-0000-0000-000000000002'; }
            public function stockLotNumber(): string { return 'CL-1'; }
        };

        $this->expectException(LogicException::class);
        app(StockLedger::class)->receive($lot, 1, 1, 'nos');
    }

    public function test_a_registered_movement_type_counts_in_the_reconciliation_and_an_unknown_one_still_needs_review(): void
    {
        $lot = $this->lot();
        app(StockLedger::class)->receive($lot, 10, 100, 'kg');
        app(StockMovementTypes::class)->register('SAMPLE_OUT', 'Sample taken', -1);
        StockMovement::create(['movement_number' => 'SMP-1', 'movement_type' => 'SAMPLE_OUT', 'material_id' => $lot->material_id, 'material_lot_id' => $lot->id,
            'quantity' => 0, 'uom' => 'kg', 'occurred_at' => now()]);

        $this->assertSame('Sample taken', app(StockMovementTypes::class)->label('SAMPLE_OUT'));
        $this->assertContains('SAMPLE_OUT', app(StockMovementTypes::class)->removing());
        $this->get(route('stock-reconciliation.index'))->assertOk()->assertDontSee('Review Required');

        StockMovement::create(['movement_number' => 'ODD-1', 'movement_type' => 'MYSTERY', 'material_id' => $lot->material_id, 'material_lot_id' => $lot->id,
            'quantity' => 1, 'uom' => 'kg', 'occurred_at' => now()]);
        $this->get(route('stock-reconciliation.index'))->assertSee('Review Required');
    }

    public function test_a_movement_type_effect_is_add_remove_or_move(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(StockMovementTypes::class)->register('BAD', 'Bad', 2);
    }
}
