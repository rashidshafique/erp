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

        $page = $this->get(route('heats.create'))->assertOk()->assertSee('Optional. Set it later on the heat')->assertDontSee('Target metal weight (kg) *', false);
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
}
