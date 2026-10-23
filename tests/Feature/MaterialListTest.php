<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Services\Stock\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaterialListTest extends TestCase
{
    use RefreshDatabase;

    private function material(array $over = []): Material
    {
        return Material::create($over + ['code' => 'MAT-'.uniqid(), 'name' => 'Steel scrap', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => true]);
    }

    private function stock(Material $material, float $quantity): void
    {
        $lot = MaterialLot::create(['lot_number' => 'L-'.uniqid(), 'material_id' => $material->id, 'status' => 'ACCEPTED', 'received_qty' => $quantity, 'accepted_qty' => $quantity, 'unit_cost' => 90, 'currency_code' => 'PKR']);
        app(StockLedger::class)->receive($lot, $quantity, 90, 'kg');
    }

    public function test_the_page_lists_materials_with_stock_on_hand_and_flags_the_ones_at_minimum(): void
    {
        $low = $this->material(['code' => 'FESI-75', 'name' => 'Ferro silicon', 'minimum_stock_qty' => 100]);
        $this->stock($low, 40);
        $fine = $this->material(['code' => 'COKE', 'name' => 'Foundry coke', 'minimum_stock_qty' => 100]);
        $this->stock($fine, 900);

        $this->get(route('materials.index'))->assertOk()->assertSee('FESI-75')->assertSee('COKE')->assertSee('+ New material');

        Livewire::test('material-list')
            ->assertSee('40.000')->assertSee('900.000')->assertViewHas('lowCount', 1)
            ->set('stock', 'low')->assertSee('FESI-75')->assertDontSee('COKE');
    }

    public function test_search_type_and_status_filters_apply_as_they_are_set(): void
    {
        $this->material(['code' => 'FESI-75', 'name' => 'Ferro silicon', 'material_type' => 'ALLOY']);
        $this->material(['code' => 'COKE', 'name' => 'Foundry coke', 'material_type' => 'FUEL']);
        $this->material(['code' => 'OLDSAND', 'name' => 'Old sand', 'material_type' => 'SAND', 'status' => 'INACTIVE']);

        $list = Livewire::test('material-list');
        $list->set('search', 'ferro');
        $this->assertSame(['FESI-75'], $list->viewData('materials')->pluck('code')->all(), 'search');
        $list->set('search', '')->set('type', 'FUEL');
        $this->assertSame(['COKE'], $list->viewData('materials')->pluck('code')->all(), 'type');
        $list->set('type', '')->set('status', 'INACTIVE');
        $this->assertSame(['OLDSAND'], $list->viewData('materials')->pluck('code')->all(), 'status');
        $list->call('resetFilters');
        $this->assertSame(['COKE', 'FESI-75', 'OLDSAND'], $list->viewData('materials')->pluck('code')->all(), 'reset');
    }

    public function test_a_material_is_added_from_the_dialog_with_the_same_rules_as_the_form_page(): void
    {
        $this->material(['code' => 'TAKEN']);

        $dialog = Livewire::test('material-list')->call('create')->assertSet('formOpen', true)->assertSeeHtml('<dialog class="qd"');

        $dialog->call('save')->assertHasErrors('form.name')->assertHasNoErrors('form.code')->assertSet('formOpen', true);
        $dialog->set('form.code', 'TAKEN')->set('form.name', 'Dup')->call('save')->assertHasErrors('form.code');
        $dialog->set('form.code', 'BENT-1')->set('form.name', 'Bentonite')->set('form.material_type', 'GOLD')->call('save')->assertHasErrors('form.material_type');

        $dialog->set('form.material_type', 'BINDER')->set('form.base_uom', 'bag')->set('form.minimum_stock_qty', '50')->set('form.standard_cost_per_uom', '75.5')
            ->call('save')->assertHasNoErrors()->assertSet('formOpen', false)->assertSee('Material BENT-1 added.');

        $material = Material::where('code', 'BENT-1')->sole();
        $this->assertSame('BINDER', $material->material_type);
        $this->assertSame('bag', $material->base_uom);
        $this->assertEquals(50, $material->minimum_stock_qty);
        $this->assertNull($material->reorder_qty);
        $this->assertTrue($material->is_stock_tracked);
        $this->assertNull($material->description);
    }

    public function test_a_material_is_quick_edited_in_the_dialog_keeping_its_own_code(): void
    {
        $material = $this->material(['code' => 'FESI-75', 'name' => 'Ferro silicon', 'minimum_stock_qty' => 100]);
        $this->material(['code' => 'OTHER']);

        Livewire::test('material-list')
            ->call('edit', $material->id)->assertSet('form.code', 'FESI-75')->assertSet('form.minimum_stock_qty', '100.000')
            ->set('form.name', 'Ferro silicon 72')->call('save')->assertHasNoErrors()->assertSee('Material FESI-75 updated.')
            ->call('edit', $material->id)->set('form.code', 'OTHER')->call('save')->assertHasErrors('form.code');

        $this->assertSame('Ferro silicon 72', $material->fresh()->name);
    }

    public function test_adding_a_material_with_a_minimum_and_no_stock_raises_the_reorder_check(): void
    {
        Livewire::test('material-list')->call('create')->set('form.code', 'NEW')->set('form.name', 'New alloy')->set('form.minimum_stock_qty', '10')->call('save')->assertHasNoErrors();

        $this->assertNotNull(Material::where('code', 'NEW')->sole()->reorder_alerted_at);
    }

    public function test_the_form_pages_and_material_page_use_the_shared_screen_styles(): void
    {
        $material = $this->material(['code' => 'FESI-75']);

        foreach ([route('materials.create'), route('materials.edit', $material), route('materials.show', $material), route('materials.lots.create', $material)] as $url) {
            $this->get($url)->assertOk()->assertDontSee('page-header')->assertDontSee('form-control')->assertDontSee('btn-secondary')->assertSee('page-heading');
        }
    }

    public function test_a_blank_code_is_generated_and_a_typed_one_is_kept_in_capitals_on_both_the_dialog_and_the_form_page(): void
    {
        Livewire::test('material-list')->call('create')->assertSee('Blank: MAT-')->set('form.name', 'Bentonite')->call('save')->assertHasNoErrors();
        $generated = Material::where('name', 'Bentonite')->sole();
        $this->assertMatchesRegularExpression('/^MAT-\w+$/', $generated->code);

        Livewire::test('material-list')->call('create')->set('form.name', 'Coke')->set('form.code', ' coke-1 ')->call('save')->assertHasNoErrors();
        $this->assertNotNull(Material::where('code', 'COKE-1')->first());
        Livewire::test('material-list')->call('create')->set('form.name', 'Other coke')->set('form.code', 'Coke-1')->call('save')->assertHasErrors('form.code');

        $form = ['name' => 'Silica sand', 'material_type' => 'SAND', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => 1, 'requires_lot_tracking' => 1];
        $this->post(route('materials.store'), $form)->assertSessionHasNoErrors();
        $sand = Material::where('name', 'Silica sand')->sole();
        $this->assertMatchesRegularExpression('/^MAT-\w+$/', $sand->code);
        $this->assertNotSame($generated->code, $sand->code);

        $this->put(route('materials.update', $sand), $form + ['name' => 'Silica sand 2'])->assertSessionHasNoErrors();
        $this->assertSame($sand->code, $sand->fresh()->code, 'a blank code on edit keeps the code');
        $this->put(route('materials.update', $sand), $form + ['code' => 'sand-x'])->assertSessionHasNoErrors();
        $this->assertSame('SAND-X', $sand->fresh()->code);
        $this->post(route('materials.store'), $form + ['code' => 'sand-x'])->assertSessionHasErrors('code');

        $this->get(route('materials.create'))->assertOk()->assertSee('Leave blank to generate: MAT-');
    }
}
