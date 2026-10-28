<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockLocation;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockLocationTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockLocationTreeTest extends TestCase
{
    use RefreshDatabase;

    private function receive(StockLocation $location, float $kg): void
    {
        $material = Material::create(['code' => 'M'.uniqid(), 'name' => 'Scrap', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
        $lot = MaterialLot::create(['lot_number' => 'L'.uniqid(), 'material_id' => $material->id, 'status' => 'ACCEPTED', 'received_qty' => $kg, 'accepted_qty' => $kg]);
        app(StockLedger::class)->receive($lot, $kg, 10, 'kg', ['to_location_id' => $location->id]);
    }

    public function test_the_register_shows_sub_stores_under_their_store_with_stock_rolled_up(): void
    {
        $store = StockLocation::factory()->create(['code' => 'ST-A', 'name' => 'Alloy store']);
        $rack = StockLocation::factory()->create(['code' => 'ST-A-R1', 'name' => 'Rack one', 'location_type' => 'BIN', 'parent_location_id' => $store->id]);
        $yard = StockLocation::factory()->create(['code' => 'YD-1', 'name' => 'Yard', 'location_type' => 'YARD']);
        $this->receive($store, 100);
        $this->receive($rack, 40);
        $this->receive($yard, 5);

        $page = Livewire::test('inventory::location-list');
        $rows = $page->viewData('rows');

        $this->assertSame(['ST-A', 'ST-A-R1', 'YD-1'], array_map(fn ($r) => $r['location']->code, $rows));
        $this->assertSame([0, 1, 0], array_column($rows, 'depth'));
        $this->assertEquals([100.0, 40.0, 5.0], array_column($rows, 'own'));
        $this->assertEquals([140.0, 40.0, 5.0], array_column($rows, 'total'));
    }

    public function test_a_sub_store_whose_parent_is_switched_off_is_still_listed(): void
    {
        $store = StockLocation::factory()->create(['code' => 'ST-A', 'is_active' => false]);
        StockLocation::factory()->create(['code' => 'ST-A-R1', 'parent_location_id' => $store->id]);

        $rows = Livewire::test('inventory::location-list')->viewData('rows');

        $this->assertSame(['ST-A-R1'], array_map(fn ($r) => $r['location']->code, $rows));
        $this->assertSame(0, $rows[0]['depth']);
    }

    public function test_a_module_can_add_a_location_type_and_mark_it_as_not_ours(): void
    {
        app(StockLocationTypes::class)->register('SUBCONTRACTOR', 'Subcontractor', external: true);

        Livewire::test('inventory::location-list')
            ->call('create')->set('code', 'HT-1')->set('name', 'Heat treater')->set('locationType', 'SUBCONTRACTOR')
            ->call('save')->assertHasNoErrors()
            ->assertSee('Subcontractor')->assertSee('not ours');

        $this->assertTrue(StockLocation::where('code', 'HT-1')->sole()->isExternal());
        Livewire::test('inventory::location-list')->call('create')->set('code', 'X')->set('name', 'X')->set('locationType', 'NOPE')->call('save')->assertHasErrors('locationType');
    }
}
