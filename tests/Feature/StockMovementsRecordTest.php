<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockLocation;
use App\Models\StockLotCostLayer;
use App\Models\StockMovement;
use App\Services\Stock\InsufficientStockException;
use App\Services\Stock\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockMovementsRecordTest extends TestCase
{
    use RefreshDatabase;

    private function lotWithStock(float $quantity = 100, float $cost = 90): MaterialLot
    {
        $material = Material::create(['code' => 'M-'.uniqid(), 'name' => 'Steel scrap', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => true]);
        $lot = MaterialLot::create(['lot_number' => 'L-'.uniqid(), 'material_id' => $material->id, 'status' => 'ACCEPTED', 'received_qty' => $quantity, 'accepted_qty' => $quantity, 'unit_cost' => $cost, 'currency_code' => 'PKR']);
        app(StockLedger::class)->receive($lot, $quantity, $cost, 'kg');

        return $lot;
    }

    private function record(MaterialLot $lot, string $kind, array $fields = [])
    {
        $test = Livewire::test('inventory::record-movement')
            ->set('kind', $kind)->set('materialId', $lot->material_id)->set('lotId', $lot->id)->set('recordedBy', 'Storekeeper');
        foreach ($fields as $name => $value) {
            $test->set($name, $value);
        }

        return $test->call('save');
    }

    public function test_an_issue_draws_the_oldest_cost_layer_first_and_costs_the_movement_from_it(): void
    {
        $lot = $this->lotWithStock(100, 90);
        app(StockLedger::class)->receive($lot, 50, 120, 'kg', ['occurred_at' => now()->addMinute()]);

        $this->record($lot, 'ISSUE', ['quantity' => '120'])->assertHasNoErrors();

        $issue = StockMovement::where('movement_type', 'ISSUE')->sole();
        $this->assertSame('MANUAL_ISSUE', $issue->source_type);
        $this->assertEquals(100 * 90 + 20 * 120, (float) $issue->total_cost);
        $this->assertEquals(30, StockLotCostLayer::where('material_lot_id', $lot->id)->sum('remaining_qty'));
    }

    public function test_an_issue_larger_than_the_lot_holds_is_refused_and_changes_nothing(): void
    {
        $lot = $this->lotWithStock(10);

        $this->record($lot, 'ISSUE', ['quantity' => '11'])->assertHasErrors('quantity');

        $this->assertSame(0, StockMovement::where('movement_type', 'ISSUE')->count());
        $this->assertEquals(10, StockLotCostLayer::sum('remaining_qty'));
    }

    public function test_scrap_and_count_adjustments_need_a_reason_and_keep_the_ledger_reconciled(): void
    {
        $lot = $this->lotWithStock(100, 90);

        $this->record($lot, 'SCRAP', ['quantity' => '5'])->assertHasErrors('reason');
        $this->record($lot, 'SCRAP', ['quantity' => '5', 'reason' => 'Rusted'])->assertHasNoErrors();
        $this->record($lot, 'ADJUST_OUT', ['quantity' => '2', 'reason' => 'Count short'])->assertHasNoErrors();
        $this->record($lot, 'ADJUST_IN', ['quantity' => '4', 'unitCost' => '95', 'reason' => 'Count over'])->assertHasNoErrors();

        $this->assertEquals(97, app(StockLedger::class)->onHand($lot));
        $this->assertSame(['SCRAP', 'ADJUSTMENT', 'ADJUSTMENT'], StockMovement::orderBy('occurred_at')->orderBy('id')->skip(1)->pluck('source_type')->all());
        $this->get(route('stock-reconciliation.index'))->assertOk()->assertSee('Reconciled')->assertDontSee('Review Required')->assertDontSee('Quantity Mismatch');
    }

    public function test_a_transfer_moves_stock_between_locations_without_changing_quantity_or_layers(): void
    {
        $lot = $this->lotWithStock(100);
        [$store, $yard] = [StockLocation::factory()->create(['code' => 'STORE']), StockLocation::factory()->create(['code' => 'YARD'])];

        $this->record($lot, 'TRANSFER', ['quantity' => '40', 'toLocationId' => $store->id])->assertHasNoErrors();
        $this->record($lot, 'TRANSFER', ['quantity' => '15', 'fromLocationId' => $store->id, 'toLocationId' => $yard->id])->assertHasNoErrors();

        $this->assertEquals([$store->id => 25.0, $yard->id => 15.0, '' => 60.0], app(StockLedger::class)->heldByLocation($lot));
        $this->assertEquals(100, app(StockLedger::class)->onHand($lot));

        $this->record($lot, 'TRANSFER', ['quantity' => '30', 'fromLocationId' => $yard->id, 'toLocationId' => $store->id])->assertHasErrors('quantity');
        $this->record($lot, 'TRANSFER', ['quantity' => '1', 'fromLocationId' => $yard->id, 'toLocationId' => $yard->id])->assertHasErrors('toLocationId');
        $this->record($lot, 'TRANSFER', ['quantity' => '1'])->assertHasErrors('toLocationId');
    }

    public function test_an_issue_from_a_location_takes_it_from_that_location_only(): void
    {
        $lot = $this->lotWithStock(50);
        $store = StockLocation::factory()->create();
        app(StockLedger::class)->transfer($lot, 20, 'kg', null, $store->id);

        $this->record($lot, 'ISSUE', ['quantity' => '25', 'fromLocationId' => $store->id])->assertHasErrors('quantity');
        $this->record($lot, 'ISSUE', ['quantity' => '15', 'fromLocationId' => $store->id])->assertHasNoErrors();

        $this->assertEquals([$store->id => 5.0, '' => 30.0], app(StockLedger::class)->heldByLocation($lot));
    }

    public function test_a_lot_that_is_not_accepted_or_belongs_to_another_material_cannot_be_used(): void
    {
        $lot = $this->lotWithStock();
        $other = $this->lotWithStock();
        $lot->update(['status' => 'QUARANTINE']);

        $this->record($lot, 'ISSUE', ['quantity' => '1'])->assertHasErrors('lotId');
        Livewire::test('inventory::record-movement')->set('kind', 'ISSUE')->set('materialId', $lot->material_id)->set('lotId', $other->id)
            ->set('quantity', '1')->set('recordedBy', 'X')->call('save')->assertHasErrors('lotId');
        $this->assertSame(0, StockMovement::where('movement_type', 'ISSUE')->count());
    }

    public function test_the_ledger_refuses_to_issue_more_than_a_lot_holds(): void
    {
        $lot = $this->lotWithStock(10);

        $this->expectException(InsufficientStockException::class);
        app(StockLedger::class)->issue($lot, 10.5, 'kg');
    }

    public function test_the_balance_page_lists_stock_by_location_and_filters_it(): void
    {
        $lot = $this->lotWithStock(100);
        $store = StockLocation::factory()->create(['code' => 'STORE-9']);
        app(StockLedger::class)->transfer($lot, 30, 'kg', null, $store->id);

        $this->get(route('stock-balances.index'))->assertOk();
        $this->get(route('stock-movements.record'))->assertOk();

        Livewire::test('inventory::stock-by-location')
            ->assertSeeHtml('<td>STORE-9</td>')->assertSeeHtml('<td>Not put anywhere</td>')
            ->set('location', $store->id)->assertSeeHtml('<td>STORE-9</td>')->assertDontSeeHtml('<td>Not put anywhere</td>')
            ->set('location', 'none')->assertDontSeeHtml('<td>STORE-9</td>')->assertSeeHtml('<td>Not put anywhere</td>');
    }

    public function test_posting_a_lot_receipt_can_name_the_location_it_goes_into(): void
    {
        $store = StockLocation::factory()->create();
        $material = Material::create(['code' => 'M1', 'name' => 'Alloy', 'material_type' => 'ALLOY', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => true]);
        $lot = MaterialLot::create(['lot_number' => 'L1', 'material_id' => $material->id, 'status' => 'ACCEPTED', 'received_qty' => 40, 'accepted_qty' => 40, 'unit_cost' => 100, 'currency_code' => 'PKR']);

        $this->get(route('materials.show', $material))->assertOk()->assertSee($store->code);
        $this->post(route('materials.lots.post-receipt', [$material, $lot]), ['confirmed_physical_stock' => 1, 'recorded_by' => 'Ali', 'stock_location_id' => $store->id])->assertSessionHasNoErrors();

        $this->assertSame($store->id, StockMovement::sole()->to_location_id);
        $this->assertEquals([$store->id => 40.0], app(StockLedger::class)->heldByLocation($lot));
    }
}
