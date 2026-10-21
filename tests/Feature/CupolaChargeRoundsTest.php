<?php

namespace Tests\Feature;

use App\Models\ChargeBatch;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CupolaChargeRoundsTest extends TestCase
{
    use RefreshDatabase;

    private function setUpCupola(): array
    {
        $iron = Material::create(['code' => 'PIG', 'name' => 'Pig iron', 'material_type' => 'PIG_IRON', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
        $coke = Material::create(['code' => 'COKE', 'name' => 'Coke', 'material_type' => 'COKE', 'base_uom' => 'kg', 'status' => 'ACTIVE']);
        foreach ([$iron, $coke] as $m) {
            MaterialLot::create(['lot_number' => 'L-'.$m->code, 'material_id' => $m->id, 'received_date' => today(), 'status' => 'ACCEPTED', 'received_qty' => 99999, 'accepted_qty' => 99999, 'unit_cost' => 50, 'currency_code' => 'PKR']);
        }
        $recipe = ChargeBatch::create(['code' => 'CR-CUP', 'name' => 'Cupola', 'process_type' => 'CUPOLA', 'status' => 'ACTIVE', 'target_metal_weight_kg' => 180, 'target_total_charge_weight_kg' => 205]);
        $recipe->items()->create(['material_id' => $coke->id, 'sequence' => 1, 'component_role' => 'COKE', 'charge_stage' => 'BED', 'target_qty_kg' => 100, 'is_required' => true]);
        $recipe->items()->create(['material_id' => $iron->id, 'sequence' => 2, 'component_role' => 'CHARGE', 'charge_stage' => 'ROUND', 'target_qty_kg' => 180, 'is_required' => true]);
        $recipe->items()->create(['material_id' => $coke->id, 'sequence' => 3, 'component_role' => 'COKE', 'charge_stage' => 'ROUND', 'target_qty_kg' => 25, 'is_required' => true]);
        $furnace = Furnace::create(['code' => 'CU-1', 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE']);
        $heat = Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'PLANNED', 'charge_batch_id' => $recipe->id, 'target_metal_weight_kg' => 900]);

        return [$heat, $iron, $coke];
    }

    public function test_rounds_default_to_the_target_over_the_recipe_metal_and_can_be_changed(): void
    {
        [$heat] = $this->setUpCupola();
        $this->assertSame(5, $heat->chargeRounds());

        $this->put(route('heats.charge-rounds.update', $heat), ['charge_rounds' => 17])->assertRedirect();
        $this->assertSame(17, $heat->fresh()->chargeRounds());
    }

    public function test_bed_then_each_round_is_recorded_with_the_recipe_quantities_and_the_plan_is_multiplied(): void
    {
        [$heat, $iron, $coke] = $this->setUpCupola();
        $at = now()->format('Y-m-d\TH:i');

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Start-up (bed) charge');
        $this->post(route('heats.charge-rounds.store', $heat), ['charge_round' => 0, 'recorded_by' => 'Ali', 'consumed_at' => $at,
            'lines' => [['material_id' => $coke->id, 'actual_qty_kg' => 100]]])->assertSessionHasNoErrors();
        $this->get(route('heats.show', $heat))->assertSee('Charge 1 of 5');

        $this->post(route('heats.charge-rounds.store', $heat), ['charge_round' => 1, 'recorded_by' => 'Ali', 'consumed_at' => $at,
            'lines' => [['material_id' => $iron->id, 'actual_qty_kg' => 180], ['material_id' => $coke->id, 'actual_qty_kg' => 25]]])->assertSessionHasNoErrors();

        $this->assertSame(3, HeatMaterialConsumption::where('heat_id', $heat->id)->count());
        $this->assertSame(2, HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', 1)->count());
        $this->assertNotNull(HeatMaterialConsumption::where('charge_round', 1)->first()->material_lot_id);
        $this->get(route('heats.show', $heat))->assertSee('Charge 2 of 5')->assertSee('900.000');   // 180 kg of iron × 5 charges
    }

    public function test_a_round_cannot_be_recorded_twice(): void
    {
        [$heat, $iron] = $this->setUpCupola();
        $payload = ['charge_round' => 1, 'recorded_by' => 'Ali', 'consumed_at' => now()->format('Y-m-d\TH:i'), 'lines' => [['material_id' => $iron->id, 'actual_qty_kg' => 180]]];

        $this->post(route('heats.charge-rounds.store', $heat), $payload)->assertSessionHasNoErrors();
        $this->post(route('heats.charge-rounds.store', $heat), $payload)->assertSessionHasErrors('charge_round');
        $this->assertSame(1, HeatMaterialConsumption::where('heat_id', $heat->id)->count());
    }
}
