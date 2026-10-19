<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\IronGrade;
use App\Models\PlannedHeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FurnaceAndPlanTest extends TestCase
{
    use RefreshDatabase;

    private function furnace(string $type, string $status = 'ACTIVE'): Furnace
    {
        return Furnace::create(['code' => $type.'-'.uniqid(), 'name' => ucfirst(strtolower($type)), 'furnace_type' => $type, 'status' => $status, 'rated_capacity_kg' => 1000, 'nominal_melting_rate_kg_per_hour' => 500]);
    }

    private function plannedHeat(IronGrade $grade, Furnace $furnace): PlannedHeat
    {
        return PlannedHeat::create(['plan_number' => 'HP-'.uniqid(), 'iron_grade_id' => $grade->id, 'furnace_id' => $furnace->id, 'planned_date' => today(), 'sequence' => 1, 'planned_metal_kg' => 500, 'status' => 'PLANNED']);
    }

    public function test_a_furnace_is_added_with_a_unique_code(): void
    {
        $this->get(route('furnaces.create'))->assertOk();
        $form = ['code' => 'IF-1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 1500];

        $this->post(route('furnaces.store'), $form)->assertRedirect(route('furnaces.index'));
        $this->get(route('furnaces.index'))->assertOk()->assertSee('IF-1');

        $this->post(route('furnaces.store'), $form)->assertSessionHasErrors('code');
        $this->post(route('furnaces.store'), ['code' => 'X', 'name' => 'X', 'furnace_type' => 'KILN', 'status' => 'ACTIVE'])->assertSessionHasErrors('furnace_type');
        $this->assertSame(1, Furnace::count());
    }

    public function test_a_planned_heat_can_move_only_to_a_furnace_that_can_melt_its_grade(): void
    {
        $ductile = IronGrade::where('family', 'DUCTILE')->firstOrFail();
        $induction = $this->furnace('INDUCTION');
        $cupola = $this->furnace('CUPOLA');
        $resting = $this->furnace('INDUCTION', 'MAINTENANCE');
        $plan = $this->plannedHeat($ductile, $induction);

        $this->post(route('heat-plan.assign', $plan), ['furnace_id' => $cupola->id])->assertSessionHasErrors('furnace_id');
        $this->post(route('heat-plan.assign', $plan), ['furnace_id' => $resting->id])->assertSessionHasErrors('furnace_id');
        $this->assertSame($induction->id, $plan->fresh()->furnace_id);

        $other = $this->furnace('INDUCTION');
        $this->post(route('heat-plan.assign', $plan), ['furnace_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($other->id, $plan->fresh()->furnace_id);
    }

    public function test_a_planned_heat_that_has_moved_on_cannot_change_furnace(): void
    {
        $grey = IronGrade::where('family', 'GREY')->firstOrFail();
        $furnace = $this->furnace('CUPOLA');
        $plan = $this->plannedHeat($grey, $furnace);
        $plan->update(['status' => 'RELEASED']);

        $this->post(route('heat-plan.assign', $plan), ['furnace_id' => $this->furnace('CUPOLA')->id])->assertNotFound();
    }

    public function test_the_charge_recipe_pages_open(): void
    {
        $this->get(route('charge-batches.index'))->assertOk();
        $this->get(route('charge-batches.create'))->assertOk();
    }
}
