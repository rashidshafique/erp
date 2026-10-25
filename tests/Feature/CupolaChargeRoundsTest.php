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

    public function test_planned_charges_are_the_target_over_the_recipe_metal_rounded_up_and_never_below_those_recorded(): void
    {
        [$heat, $iron] = $this->setUpCupola();   // 900 kg at 180 kg a charge
        $this->assertSame(5, $heat->chargeRounds());

        $heat->update(['target_metal_weight_kg' => 950]);
        $this->assertSame(6, $heat->fresh()->chargeRounds(), 'A part charge is a charge.');

        // Charge 7 is recorded although the target needs six: the plan never drops below what has gone in.
        HeatMaterialConsumption::create(['heat_id' => $heat->id, 'material_id' => $iron->id, 'sequence' => 1, 'consumption_type' => 'RAW_MATERIAL', 'actual_qty_kg' => 180,
            'consumed_at' => now(), 'recorded_by' => 'Ali', 'charge_round' => 7]);
        $this->assertSame(7, $heat->fresh()->chargeRounds());
    }

    public function test_a_heat_has_no_field_to_type_the_planned_charges_over_or_a_total_charged(): void
    {
        [$heat] = $this->setUpCupola();

        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee('Planned charges on this heat')->assertDontSee('Total metal charged so far')
            ->assertSee('5 planned charges: the target of 900 kg over 180 kg of metal per charge');
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('heats.charge-rounds.update'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('heats.charge-rounds.total'));
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

    public function test_the_target_metal_can_be_changed_and_a_cupolas_planned_charges_follow_it(): void
    {
        [$heat] = $this->setUpCupola();   // 900 kg of metal at 180 kg a charge: 5 charges

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('data-target-edit title=', false)->assertSee(route('heats.target.update', $heat), false);
        $this->put(route('heats.target.update', $heat), ['target_metal_weight_kg' => 1800])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Planned charges changed from 5 to 10'));
        $this->assertSame([1800.0, 10], [(float) $heat->fresh()->target_metal_weight_kg, $heat->fresh()->chargeRounds()]);

        $this->put(route('heats.target.update', $heat), ['target_metal_weight_kg' => 900])->assertSessionHasNoErrors();
        $this->assertSame(5, $heat->fresh()->chargeRounds());
    }

    public function test_the_target_metal_must_be_positive_and_cannot_be_changed_on_a_closed_heat(): void
    {
        [$heat] = $this->setUpCupola();

        $this->put(route('heats.target.update', $heat), ['target_metal_weight_kg' => 0])->assertSessionHasErrors('target_metal_weight_kg');
        $this->put(route('heats.target.update', $heat), ['target_metal_weight_kg' => ''])->assertSessionHasErrors('target_metal_weight_kg');
        $this->assertSame(900.0, (float) $heat->fresh()->target_metal_weight_kg);

        $heat->update(['status' => 'CLOSED']);
        $this->put(route('heats.target.update', $heat), ['target_metal_weight_kg' => 500])->assertSessionHasErrors('target_metal_weight_kg');
        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee('data-target-edit title=', false);
        $this->assertSame(900.0, (float) $heat->fresh()->target_metal_weight_kg);
    }
}
