<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ChargeBatch;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatChemistryReading;
use App\Models\HeatMaterialConsumption;
use App\Models\IronGrade;
use App\Models\Material;
use App\Services\Quality\ChargePredictor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargePredictionTest extends TestCase
{
    use RefreshDatabase;

    private function material(string $code, string $type, array $chemistry = []): Material
    {
        $material = Material::create(['code' => $code, 'name' => $code, 'material_type' => $type, 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true, 'requires_lot_tracking' => false]);
        foreach ($chemistry as $element => $figures) {
            $material->elements()->create(['element' => $element] + $figures + ['recovery_pct' => 100]);
        }

        return $material;
    }

    private function grade(array $over = []): IronGrade
    {
        return IronGrade::create($over + ['code' => 'FG-'.uniqid(), 'name' => 'FG 260', 'family' => 'GREY', 'status' => 'ACTIVE',
            'c_min' => 3.2, 'c_max' => 3.6, 'si_min' => 1.8, 'si_max' => 2.4, 'mn_min' => 0.5, 'mn_max' => 0.9]);
    }

    public function test_the_prediction_weights_each_material_by_its_chemistry_and_recovery_over_the_metal_charged(): void
    {
        $pig = $this->material('PIG', 'METAL', ['c' => ['nominal_pct' => 4.0], 'si' => ['nominal_pct' => 2.0], 'mn' => ['nominal_pct' => 0.6]]);
        $scrap = $this->material('SCRAP', 'METAL', ['c' => ['nominal_pct' => 0.2], 'si' => ['nominal_pct' => 0.3], 'mn' => ['nominal_pct' => 0.5]]);
        $coke = $this->material('COKE', 'FUEL');

        $result = app(ChargePredictor::class)->predict([$pig->id => 600, $scrap->id => 400, $coke->id => 200], $this->grade());

        $this->assertEquals(1000.0, $result['metal_kg'], 'fuel is not metal');
        $this->assertEquals(1.0, $result['coverage']);
        $this->assertEquals((600 * 4.0 + 400 * 0.2) / 1000, $result['elements']['c']['nominal']);   // 2.48
        $this->assertEquals((600 * 2.0 + 400 * 0.3) / 1000, $result['elements']['si']['nominal']);   // 1.32
        $this->assertSame('OUT', $result['elements']['c']['status']);
        $this->assertSame('DEVIATES', $result['verdict']);
        $this->assertEquals(-0.72, $result['elements']['c']['gap']);   // 2.48 against the 3.2 minimum
    }

    public function test_recovery_scales_what_reaches_the_iron_and_the_spread_widens_the_range(): void
    {
        $iron = $this->material('PIG', 'METAL', [
            'c' => ['nominal_pct' => 3.4], 'si' => ['nominal_pct' => 2.4, 'min_pct' => 2.0, 'max_pct' => 2.8, 'recovery_pct' => 90], 'mn' => ['nominal_pct' => 0.7],
        ]);

        $result = app(ChargePredictor::class)->predict([$iron->id => 1000], $this->grade());

        $this->assertEquals(2.16, $result['elements']['si']['nominal']);   // 2.4 x 0.9
        $this->assertEquals(1.8, $result['elements']['si']['low']);
        $this->assertEquals(2.52, $result['elements']['si']['high']);
        $this->assertSame('AT_RISK', $result['elements']['si']['status'], 'typical is inside the window but the high end is over it');
        $this->assertSame('AT_RISK', $result['verdict']);
    }

    public function test_a_charge_whose_whole_spread_is_inside_conforms_and_a_limited_element_with_no_data_makes_it_partial(): void
    {
        $iron = $this->material('PIG', 'METAL', ['c' => ['nominal_pct' => 3.4], 'si' => ['nominal_pct' => 2.1], 'mn' => ['nominal_pct' => 0.7]]);
        $predictor = app(ChargePredictor::class);

        $this->assertSame('CONFORMS', $predictor->predict([$iron->id => 1000], $this->grade())['verdict']);
        $this->assertSame('PARTIAL', $predictor->predict([$iron->id => 1000], $this->grade(['s_max' => 0.12]))['verdict']);
        $this->assertSame('NO_GRADE', $predictor->predict([$iron->id => 1000], null)['verdict']);
    }

    public function test_carbon_equivalent_is_predicted_and_judged_against_the_grade(): void
    {
        $iron = $this->material('PIG', 'METAL', ['c' => ['nominal_pct' => 3.4], 'si' => ['nominal_pct' => 2.1], 'p' => ['nominal_pct' => 0.3]]);

        $ce = app(ChargePredictor::class)->predict([$iron->id => 500], $this->grade(['ce_min' => 3.8, 'ce_max' => 4.1]))['ce'];

        $this->assertEquals(4.2, $ce['nominal']);   // 3.4 + (2.1 + 0.3) / 3
        $this->assertSame('OUT', $ce['status']);
    }

    public function test_materials_without_chemistry_count_against_coverage_and_too_little_means_no_verdict(): void
    {
        $known = $this->material('PIG', 'METAL', ['c' => ['nominal_pct' => 3.4], 'si' => ['nominal_pct' => 2.1], 'mn' => ['nominal_pct' => 0.7]]);
        $unknown = $this->material('MYSTERY', 'METAL');
        $predictor = app(ChargePredictor::class);

        $result = $predictor->predict([$known->id => 700, $unknown->id => 300], $this->grade());
        $this->assertEquals(0.7, $result['coverage']);
        $this->assertEquals(700, $result['covered_kg']);
        $this->assertEquals(3.4, $result['elements']['c']['nominal'], 'the unknown material is left out, not counted as nothing');
        $this->assertFalse($result['enough_data']);
        $this->assertSame('INSUFFICIENT', $result['verdict']);
        $this->assertStringContainsString('MYSTERY', implode(' ', $result['notes']));

        AppSetting::setValue('charge_prediction_min_coverage', '60');
        $this->assertSame('CONFORMS', $predictor->predict([$known->id => 700, $unknown->id => 300], $this->grade())['verdict']);
    }

    public function test_the_heat_page_shows_the_prediction_from_the_recipe_and_what_is_charged_beside_the_measured_reading(): void
    {
        $pig = $this->material('PIG', 'METAL', ['c' => ['nominal_pct' => 3.4], 'si' => ['nominal_pct' => 2.1], 'mn' => ['nominal_pct' => 0.7]]);
        $grade = $this->grade();
        $recipe = ChargeBatch::create(['code' => 'CR-1', 'name' => 'Induction', 'process_type' => 'INDUCTION', 'status' => 'ACTIVE', 'target_metal_weight_kg' => 1000, 'target_total_charge_weight_kg' => 1000]);
        $recipe->items()->create(['material_id' => $pig->id, 'sequence' => 1, 'component_role' => 'CHARGE', 'charge_stage' => 'ROUND', 'target_qty_kg' => 1000, 'is_required' => true]);
        $furnace = Furnace::create(['code' => 'IF-1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $heat = Heat::create(['heat_number' => 'H-1', 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'MELTING', 'charge_batch_id' => $recipe->id, 'iron_grade_id' => $grade->id, 'target_metal_weight_kg' => 1000]);

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Predicted composition')->assertSee('On spec')->assertSee('from the recipe')->assertSee('nothing is held or refused');

        HeatMaterialConsumption::create(['heat_id' => $heat->id, 'material_id' => $pig->id, 'sequence' => 1, 'consumption_type' => 'RAW_MATERIAL', 'actual_qty_kg' => 1000, 'consumed_at' => now(), 'recorded_by' => 'Ali']);
        HeatChemistryReading::create(['heat_id' => $heat->id, 'reading_number' => 1, 'sample_stage' => 'PRE_TAP', 'test_method' => 'SPECTRO', 'carbon_percent' => 3.4, 'silicon_percent' => 1.5, 'manganese_percent' => 0.7, 'tested_at' => now()]);

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('from what is charged')->assertSee('below the prediction')->assertSee('the range the charge predicted');
    }

    public function test_a_predicted_deviation_blocks_nothing(): void
    {
        $pig = $this->material('PIG', 'METAL', ['c' => ['nominal_pct' => 1.0], 'si' => ['nominal_pct' => 0.5], 'mn' => ['nominal_pct' => 0.1]]);
        $pig->update(['standard_cost_per_uom' => 50]);
        $recipe = ChargeBatch::create(['code' => 'CR-3', 'name' => 'Off spec', 'process_type' => 'INDUCTION', 'status' => 'ACTIVE', 'target_metal_weight_kg' => 100, 'target_total_charge_weight_kg' => 100]);
        $recipe->items()->create(['material_id' => $pig->id, 'sequence' => 1, 'component_role' => 'CHARGE', 'charge_stage' => 'ROUND', 'target_qty_kg' => 100, 'is_required' => true]);
        $furnace = Furnace::create(['code' => 'IF-3', 'name' => 'Induction 3', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $heat = Heat::create(['heat_number' => 'H-3', 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'MELTING', 'charge_batch_id' => $recipe->id, 'iron_grade_id' => $this->grade()->id]);

        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Warning: predicted off spec');

        $this->post(route('heats.charge-entries.store', $heat), ['material_id' => $pig->id, 'actual_qty_kg' => 100, 'consumption_type' => 'RAW_MATERIAL', 'recorded_by' => 'Ali', 'consumed_at' => now()->toDateTimeString()])->assertSessionHasNoErrors();
        $this->assertSame(1, HeatMaterialConsumption::where('heat_id', $heat->id)->count(), 'the off-spec charge was still recorded');
    }

    public function test_no_panel_appears_when_no_material_has_chemistry(): void
    {
        $plain = $this->material('PLAIN', 'METAL');
        $recipe = ChargeBatch::create(['code' => 'CR-2', 'name' => 'Plain', 'process_type' => 'INDUCTION', 'status' => 'ACTIVE', 'target_metal_weight_kg' => 100, 'target_total_charge_weight_kg' => 100]);
        $recipe->items()->create(['material_id' => $plain->id, 'sequence' => 1, 'component_role' => 'CHARGE', 'charge_stage' => 'ROUND', 'target_qty_kg' => 100, 'is_required' => true]);
        $furnace = Furnace::create(['code' => 'IF-2', 'name' => 'Induction 2', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $heat = Heat::create(['heat_number' => 'H-2', 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'MELTING', 'charge_batch_id' => $recipe->id, 'iron_grade_id' => $this->grade()->id]);

        $this->get(route('heats.show', $heat))->assertOk()->assertDontSee('Predicted composition');
    }

    public function test_chemistry_is_saved_with_the_material_form_and_optional(): void
    {
        $form = ['code' => 'PIG-1', 'name' => 'Pig iron', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => 1, 'requires_lot_tracking' => 1];

        $this->post(route('materials.store'), $form)->assertSessionHasNoErrors();   // no chemistry panel at all
        $material = Material::where('code', 'PIG-1')->sole();
        $this->assertSame(0, $material->elements()->count());

        $chemistry = ['c' => ['nominal_pct' => '3.9', 'min_pct' => '3.7', 'max_pct' => '4.1', 'recovery_pct' => ''], 'si' => ['nominal_pct' => '2.2', 'min_pct' => '', 'max_pct' => '', 'recovery_pct' => '90'], 'mn' => ['nominal_pct' => '', 'min_pct' => '', 'max_pct' => '', 'recovery_pct' => '']];
        $this->put(route('materials.update', $material), $form + ['chemistry' => $chemistry])->assertSessionHasNoErrors();

        $this->assertEquals(['c', 'si'], $material->elements()->orderBy('element')->pluck('element')->all());
        $c = $material->elements()->where('element', 'c')->first();
        $this->assertEquals(100, $c->recovery_pct);
        $this->assertEquals(3.7, $c->min_pct);
        $this->assertNull($material->elements()->where('element', 'si')->first()->min_pct);

        $this->get(route('materials.edit', $material))->assertOk()->assertSee('Chemistry');
        $this->get(route('materials.show', $material))->assertOk()->assertSee('Typical %');

        $this->put(route('materials.update', $material), $form + ['name' => 'Pig iron 2'])->assertSessionHasNoErrors();   // no panel sent: chemistry untouched
        $this->assertSame(2, $material->elements()->count());

        $this->put(route('materials.update', $material), $form + ['chemistry' => ['c' => ['nominal_pct' => '', 'min_pct' => '', 'max_pct' => '', 'recovery_pct' => ''], 'si' => $chemistry['si']]]);
        $this->assertSame(['si'], $material->elements()->pluck('element')->all(), 'clearing the typical value removes the element');
    }

    public function test_inconsistent_chemistry_is_refused(): void
    {
        $material = $this->material('PIG', 'METAL');
        $form = ['code' => 'PIG', 'name' => 'Pig', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => 1, 'requires_lot_tracking' => 1];
        $row = fn ($over) => ['chemistry' => ['c' => $over + ['nominal_pct' => '', 'min_pct' => '', 'max_pct' => '', 'recovery_pct' => '']]];

        $this->put(route('materials.update', $material), $form + $row(['nominal_pct' => '3.0', 'min_pct' => '3.5']))->assertSessionHasErrors('chemistry.c.nominal_pct');
        $this->put(route('materials.update', $material), $form + $row(['min_pct' => '3.0']))->assertSessionHasErrors('chemistry.c.nominal_pct');
        $this->put(route('materials.update', $material), $form + $row(['nominal_pct' => '120']))->assertSessionHasErrors('chemistry.c.nominal_pct');
        $this->put(route('materials.update', $material), $form + $row(['nominal_pct' => '3', 'recovery_pct' => '400']))->assertSessionHasErrors('chemistry.c.recovery_pct');
        $this->assertSame(0, $material->elements()->count());
    }
}
