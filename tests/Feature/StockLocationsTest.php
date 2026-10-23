<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\StockLocation;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockLocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_register_page_lists_locations_and_searches_them(): void
    {
        StockLocation::factory()->create(['code' => 'STORE-A', 'name' => 'Alloy store']);
        StockLocation::factory()->create(['code' => 'YARD-1', 'name' => 'Scrap yard']);

        $this->get(route('stock-locations.index'))->assertOk()->assertSee('Alloy store');

        Livewire::test('inventory::location-list')
            ->set('search', 'yard')
            ->assertSee('YARD-1')
            ->assertDontSee('STORE-A');
    }

    public function test_a_location_is_added_under_a_parent_and_edited_with_a_unique_code(): void
    {
        $store = StockLocation::factory()->create(['code' => 'STORE-A']);

        Livewire::test('inventory::location-list')
            ->call('create')
            ->set('code', 'BIN-1')->set('name', 'Bin one')->set('locationType', 'BIN')->set('parentId', $store->id)
            ->call('save')->assertHasNoErrors();

        $bin = StockLocation::where('code', 'BIN-1')->sole();
        $this->assertSame($store->id, $bin->parent_location_id);
        $this->assertTrue($bin->is_active);

        Livewire::test('inventory::location-list')
            ->call('edit', $bin->id)->set('code', 'STORE-A')->call('save')->assertHasErrors('code')
            ->set('code', 'BIN-1')->set('name', 'Bin 1 renamed')->call('save')->assertHasNoErrors();
        $this->assertSame('Bin 1 renamed', $bin->fresh()->name);
    }

    public function test_a_location_cannot_sit_under_itself_or_a_sub_location(): void
    {
        $store = StockLocation::factory()->create();
        $bin = StockLocation::factory()->create(['parent_location_id' => $store->id]);

        Livewire::test('inventory::location-list')
            ->call('edit', $store->id)->set('parentId', $bin->id)->call('save')->assertHasErrors('parentId')
            ->set('parentId', $store->id)->call('save')->assertHasErrors('parentId');
    }

    public function test_a_location_in_use_is_switched_off_not_deleted(): void
    {
        $used = StockLocation::factory()->create();
        $free = StockLocation::factory()->create();
        $material = Material::create(['code' => 'M1', 'name' => 'Scrap', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
        $lot = MaterialLot::create(['lot_number' => 'L1', 'material_id' => $material->id, 'status' => 'ACCEPTED', 'received_qty' => 10, 'accepted_qty' => 10]);
        StockMovement::create(['movement_number' => 'MV-1', 'movement_type' => 'RECEIPT', 'material_id' => $material->id, 'material_lot_id' => $lot->id,
            'to_location_id' => $used->id, 'quantity' => 10, 'uom' => 'kg', 'occurred_at' => now()]);

        Livewire::test('inventory::location-list')
            ->call('delete', $used->id)->assertHasErrors('delete')
            ->call('delete', $free->id)->assertHasNoErrors()
            ->call('toggleActive', $used->id);

        $this->assertNotNull($used->fresh());
        $this->assertFalse($used->fresh()->is_active);
        $this->assertNull(StockLocation::find($free->id));
    }
}
