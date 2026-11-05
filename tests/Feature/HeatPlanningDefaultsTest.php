<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatPlanningDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function grade(array $over = []): IronGrade
    {
        return IronGrade::create($over + ['code' => 'G-'.uniqid(), 'name' => 'Grade', 'family' => 'GREY', 'status' => 'ACTIVE']);
    }

    public function test_a_new_heat_needs_neither_a_target_weight_nor_a_temperature(): void
    {
        $furnace = Furnace::create(['code' => 'IF-1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        $this->post(route('heats.store'), ['furnace_id' => $furnace->id, 'operator_name' => 'Ali'])->assertSessionHasNoErrors();

        $heat = Heat::sole();
        $this->assertNull($heat->target_metal_weight_kg);
        $this->assertNull($heat->target_temperature_c);
        $this->get(route('heats.show', $heat))->assertOk();
        $this->get(route('heats.index'))->assertOk()->assertSee($heat->heat_number);

        $this->post(route('heats.store'), ['furnace_id' => $furnace->id, 'operator_name' => 'Ali', 'target_metal_weight_kg' => '0'])->assertSessionHasErrors('target_metal_weight_kg');
        $this->post(route('heats.store'), ['furnace_id' => $furnace->id, 'operator_name' => 'Ali', 'target_metal_weight_kg' => '1500', 'target_temperature_c' => '1490'])->assertSessionHasNoErrors();
        $this->assertSame(2, Heat::count());
    }

    public function test_the_form_marks_weight_optional_and_offers_a_temperature_per_grade(): void
    {
        Furnace::create(['code' => 'IF-1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $ranged = $this->grade(['code' => 'FG-260', 'tap_temp_min_c' => 1450, 'tap_temp_max_c' => 1500]);
        $ductile = $this->grade(['code' => 'SG-500', 'family' => 'DUCTILE']);

        $page = $this->get(route('heats.create'))->assertOk()->assertDontSee('Optional. Set it later on the heat')->assertDontSee('Target metal weight (kg) *', false);
        $page->assertSee('"'.$ranged->id.'":{"temp":1475', false)->assertSee('"'.$ductile->id.'":{"temp":1500', false);
    }

    public function test_a_grade_suggests_the_middle_of_its_tap_range_else_its_one_figure_else_its_family_default(): void
    {
        $this->assertSame(1475, $this->grade(['tap_temp_min_c' => 1450, 'tap_temp_max_c' => 1500])->suggestedTapTemperature()['temp']);
        $this->assertSame(1520, $this->grade(['tap_temp_max_c' => 1520])->suggestedTapTemperature()['temp']);

        $grey = $this->grade()->suggestedTapTemperature();
        $this->assertSame(1480, $grey['temp']);
        $this->assertStringContainsString('grey iron', $grey['source']);
        $this->assertSame(1500, $this->grade(['family' => 'DUCTILE'])->suggestedTapTemperature()['temp']);
        $this->assertNull((new IronGrade(['code' => 'X', 'family' => 'UNKNOWN']))->suggestedTapTemperature());
    }

    public function test_the_default_furnace_and_its_grade_are_preselected_on_a_new_heat(): void
    {
        $grade = $this->grade(['code' => 'FG-DEF']);
        $other = Furnace::create(['code' => 'IF-A', 'name' => 'Induction A', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $default = Furnace::create(['code' => 'IF-B', 'name' => 'Induction B', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        $this->put(route('furnaces.defaults', $default), ['is_default' => 1, 'default_iron_grade_id' => $grade->id])->assertRedirect();
        $this->put(route('furnaces.defaults', $other), ['is_default' => 1])->assertRedirect();
        $this->assertFalse($default->fresh()->is_default);

        $this->put(route('furnaces.defaults', $default), ['is_default' => 1, 'default_iron_grade_id' => $grade->id]);
        $this->assertFalse($other->fresh()->is_default);

        $page = $this->get(route('heats.create'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option\s+value="'.$default->id.'"[^>]*selected/s', $page);
        $this->assertDoesNotMatchRegularExpression('/<option\s+value="'.$other->id.'"[^>]*selected/s', $page);
        $this->assertMatchesRegularExpression('/<option value="'.$grade->id.'" selected/', $page);

        $default->update(['status' => 'MAINTENANCE']);
        $this->assertDoesNotMatchRegularExpression('/<option\s+value="'.$default->id.'"[^>]*selected/s', $this->get(route('heats.create'))->getContent());
    }

    public function test_a_furnace_default_recipe_is_preselected_on_a_new_heat_and_only_for_its_own_process(): void
    {
        $furnace = Furnace::create(['code' => 'IF-R', 'name' => 'Induction R', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $recipe = \App\Models\ChargeBatch::create(['code' => 'CR-IND', 'name' => 'Induction mix', 'process_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $cupola = \App\Models\ChargeBatch::create(['code' => 'CR-CUP', 'name' => 'Cupola mix', 'process_type' => 'CUPOLA', 'status' => 'ACTIVE']);

        $this->put(route('furnaces.defaults', $furnace), ['is_default' => 1, 'default_charge_batch_id' => $cupola->id])->assertSessionHasErrors('default_charge_batch_id');
        $this->put(route('furnaces.defaults', $furnace), ['is_default' => 1, 'default_charge_batch_id' => $recipe->id])->assertSessionHasNoErrors();
        $this->assertSame($recipe->id, $furnace->fresh()->default_charge_batch_id);

        $page = $this->get(route('heats.create'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$recipe->id.'"[^>]*selected/s', $page);
        $this->assertStringContainsString('data-recipe="'.$recipe->id.'"', $page);

        $this->put(route('furnaces.defaults', $furnace), ['is_default' => 1])->assertRedirect();
        $this->assertNull($furnace->fresh()->default_charge_batch_id);
    }
}
