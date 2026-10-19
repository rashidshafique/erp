<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockLotCostLayer;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function form(array $over = []): array
    {
        return $over + ['code' => 'FESI-75', 'name' => 'Ferro silicon 75', 'material_type' => 'ALLOY', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => 1, 'requires_lot_tracking' => 1];
    }

    private function material(array $over = []): Material
    {
        return Material::create($over + ['code' => 'MAT-'.uniqid(), 'name' => 'Steel scrap', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => true]);
    }

    private function lot(Material $material, array $over = []): MaterialLot
    {
        return MaterialLot::create($over + ['lot_number' => 'L-'.uniqid(), 'material_id' => $material->id, 'status' => 'ACCEPTED', 'received_qty' => 50, 'accepted_qty' => 50, 'unit_cost' => 90, 'currency_code' => 'PKR']);
    }

    public function test_a_material_is_added_listed_and_edited_with_a_unique_code(): void
    {
        $this->get(route('materials.create'))->assertOk();
        $this->post(route('materials.store'), $this->form())->assertSessionHasNoErrors();
        $material = Material::sole();
        $this->get(route('materials.index'))->assertOk()->assertSee('FESI-75');

        $this->post(route('materials.store'), $this->form())->assertSessionHasErrors('code');
        $this->post(route('materials.store'), $this->form(['code' => 'X2', 'material_type' => 'GOLD']))->assertSessionHasErrors('material_type');

        $this->get(route('materials.edit', $material))->assertOk();
        $this->put(route('materials.update', $material), $this->form(['name' => 'Ferro silicon 72']))->assertSessionHasNoErrors();
        $this->assertSame('Ferro silicon 72', $material->fresh()->name);

        $other = $this->material(['code' => 'OTHER']);
        $this->put(route('materials.update', $other), $this->form())->assertSessionHasErrors('code');   // taken by FESI-75
        $this->put(route('materials.update', $material), $this->form())->assertSessionHasNoErrors();      // keeping its own code is fine
    }

    public function test_the_new_lot_form_opens_and_a_lot_cannot_accept_more_than_was_received(): void
    {
        $material = $this->material();

        $this->get(route('materials.lots.create', $material))->assertOk();
        $this->post(route('materials.lots.store', $material), [
            'lot_number' => 'L-1', 'status' => 'ACCEPTED', 'received_qty' => 10, 'accepted_qty' => 12, 'currency_code' => 'PKR',
        ])->assertSessionHasErrors('accepted_qty');
        $this->assertSame(0, MaterialLot::count());
    }

    public function test_posting_a_receipt_adds_stock_once(): void
    {
        $material = $this->material();
        $lot = $this->lot($material);
        $form = ['confirmed_physical_stock' => 1, 'recorded_by' => 'Stores'];

        $this->post(route('materials.lots.post-receipt', [$material, $lot]), $form)->assertSessionHas('success');
        $this->post(route('materials.lots.post-receipt', [$material, $lot]), $form)->assertSessionHas('info');

        $this->assertSame(1, StockMovement::where('receipt_lot_id', $lot->id)->count());
        $this->assertSame(50.0, (float) StockLotCostLayer::where('material_lot_id', $lot->id)->sum('remaining_qty'));
        $this->assertSame(4500.0, (float) StockMovement::where('receipt_lot_id', $lot->id)->value('total_cost'));
    }

    public function test_a_receipt_is_refused_for_an_unaccepted_unpriced_or_untracked_lot_or_without_confirmation(): void
    {
        $material = $this->material();
        $form = ['confirmed_physical_stock' => 1, 'recorded_by' => 'Stores'];

        $quarantined = $this->lot($material, ['status' => 'QUARANTINE']);
        $this->post(route('materials.lots.post-receipt', [$material, $quarantined]), $form)->assertSessionHasErrors('confirmed_physical_stock');

        $unpriced = $this->lot($material, ['unit_cost' => null]);
        $this->post(route('materials.lots.post-receipt', [$material, $unpriced]), $form)->assertSessionHasErrors('confirmed_physical_stock');

        $ready = $this->lot($material);
        $this->post(route('materials.lots.post-receipt', [$material, $ready]), ['recorded_by' => 'Stores'])->assertSessionHasErrors('confirmed_physical_stock');

        $untracked = $this->material(['is_stock_tracked' => false]);
        $this->post(route('materials.lots.post-receipt', [$untracked, $this->lot($untracked)]), $form)->assertSessionHasErrors('confirmed_physical_stock');

        $this->assertSame(0, StockMovement::count());
    }

    public function test_a_lot_cannot_be_posted_through_another_material(): void
    {
        $lot = $this->lot($this->material());

        $this->post(route('materials.lots.post-receipt', [$this->material(), $lot]), ['confirmed_physical_stock' => 1, 'recorded_by' => 'Stores'])->assertNotFound();
    }
}
