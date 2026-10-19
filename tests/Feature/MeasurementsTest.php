<?php

namespace Tests\Feature;

use App\Models\CharacteristicLimit;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\MeasurementSet;
use App\Services\Quality\Measurements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementsTest extends TestCase
{
    use RefreshDatabase;

    private function heat(): Heat
    {
        $f = Furnace::create(['code' => 'IF-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => 'H-M1', 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED', 'iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id')]);
    }

    public function test_grade_limits_were_seeded_from_the_grade(): void
    {
        $g = IronGrade::where('code', 'EN-GJS-500-7')->first();
        $l = CharacteristicLimit::where('subject_id', $g->id)->where('characteristic_key', 'tensile_mpa')->first();
        $this->assertNotNull($l);
        $this->assertEquals(500, $l->min_value);
        $this->assertFalse($l->is_required_for_release);
        $this->get(route('iron-grades.show', $g))->assertOk()->assertSee('Test requirements')->assertSee('Tensile strength Rm');
    }

    public function test_results_are_judged_and_limits_are_kept_with_the_result(): void
    {
        $heat = $this->heat();
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Mechanical and microstructure tests');
        $this->post(route('heats.tests.store', [$heat, 'MECHANICAL']), ['sample_reference' => 'Y1', 'sample_type' => 'SEPARATELY_CAST', 'values' => ['tensile_mpa' => '520', 'yield_mpa' => '330', 'elongation_pct' => '9', 'hardness_hb' => '190']])->assertSessionHasNoErrors();
        $set = MeasurementSet::firstOrFail();
        $this->assertSame('PASS', $set->result);
        $this->assertEquals(500, $set->measurements->firstWhere('characteristic_key', 'tensile_mpa')->min_value);
        $this->assertNull($set->measurements->firstWhere('characteristic_key', 'hardness_hb')->passed);   // no limit set, recorded but not judged

        $this->post(route('heats.tests.store', [$heat, 'MECHANICAL']), ['values' => ['tensile_mpa' => '450', 'elongation_pct' => '9']])->assertSessionHasNoErrors();
        $bad = MeasurementSet::whereNull('sample_reference')->firstOrFail();
        $this->assertSame('FAIL', $bad->result);
        $this->assertStringContainsString('below the minimum', $bad->findings[0]);

        // Changing the grade limit later leaves the old result's limits alone
        CharacteristicLimit::where('characteristic_key', 'tensile_mpa')->update(['min_value' => 600]);
        $this->assertEquals(500, $set->fresh()->measurements->firstWhere('characteristic_key', 'tensile_mpa')->min_value);

        $this->post(route('heats.tests.store', [$heat, 'MICROSTRUCTURE']), ['values' => ['nodularity_pct' => '88', 'graphite_form' => 'VI']])->assertSessionHasNoErrors();
        $this->assertSame('NOT_JUDGED', MeasurementSet::where('kind', 'MICROSTRUCTURE')->first()->result);
        $this->post(route('heats.tests.store', [$heat, 'MECHANICAL']), ['values' => ['tensile_mpa' => 'abc']])->assertSessionHasErrors('test_MECHANICAL');
        $this->post(route('heats.tests.store', [$heat, 'MECHANICAL']), ['values' => ['tensile_mpa' => '']])->assertSessionHasErrors('test_MECHANICAL');
        $this->post(route('heats.tests.store', [$heat, 'SAND']), ['values' => ['x' => '1']])->assertNotFound();
    }

    public function test_required_tests_block_release_until_a_passing_result_exists(): void
    {
        $heat = $this->heat();
        $svc = app(Measurements::class);
        $this->assertSame([], $svc->missingForRelease($heat));
        CharacteristicLimit::where('subject_id', $heat->iron_grade_id)->where('characteristic_key', 'tensile_mpa')->update(['is_required_for_release' => true]);
        $this->assertCount(1, $svc->missingForRelease($heat));
        $this->post(route('heats.tests.store', [$heat, 'MECHANICAL']), ['values' => ['tensile_mpa' => '450']]);
        $this->assertStringContainsString('outside the limits', $svc->missingForRelease($heat)[0]);
        $this->post(route('heats.tests.store', [$heat, 'MECHANICAL']), ['values' => ['tensile_mpa' => '510']]);
        $this->assertSame([], $svc->missingForRelease($heat));
    }

    public function test_limits_can_be_added_edited_and_removed(): void
    {
        $g = IronGrade::where('code', 'EN-GJS-500-7')->first();
        $this->post(route('limits.store'), ['subject_type' => 'IRON_GRADE', 'subject_id' => $g->id, 'test_group' => 'MECHANICAL', 'name' => 'Hardness', 'characteristic_key' => 'hardness_hb', 'min_value' => 170, 'max_value' => 230, 'unit' => 'HB'])->assertSessionHasNoErrors();
        $this->post(route('limits.store'), ['subject_type' => 'IRON_GRADE', 'subject_id' => $g->id, 'test_group' => 'MECHANICAL', 'name' => 'Hardness', 'characteristic_key' => 'hardness_hb'])->assertSessionHasErrors('limit');
        $this->post(route('limits.store'), ['subject_type' => 'IRON_GRADE', 'subject_id' => $g->id, 'test_group' => 'MECHANICAL', 'name' => 'Hardness', 'characteristic_key' => 'hb2', 'min_value' => 300, 'max_value' => 200])->assertSessionHasErrors('max_value');
        $l = CharacteristicLimit::where('characteristic_key', 'hardness_hb')->firstOrFail();
        $this->put(route('limits.update', $l), ['min_value' => 180, 'max_value' => 240, 'unit' => 'HB', 'is_required_for_release' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($l->fresh()->is_required_for_release);
        $this->delete(route('limits.destroy', $l))->assertSessionHasNoErrors();
        $this->assertNull(CharacteristicLimit::find($l->id));
    }

    public function test_sand_lab_records_judges_and_reports_on_the_run(): void
    {
        $sand = \App\Models\SandSystem::where('name', 'Sheera')->firstOrFail();
        $this->get(route('sand-tests.index'))->assertOk()->assertSee('Sand lab');
        $this->post(route('limits.store'), ['subject_type' => 'SAND_SYSTEM', 'subject_id' => $sand->id, 'test_group' => 'SAND', 'name' => 'Moisture', 'characteristic_key' => 'moisture_pct', 'min_value' => 3, 'max_value' => 5, 'unit' => '%'])->assertSessionHasNoErrors();
        $this->get(route('sand-tests.index', ['system' => $sand->id]))->assertOk()->assertSee('limit 3');

        $status = app(Measurements::class)->sandStatus('Sheera', now());
        $this->assertSame('NONE', $status['state']);

        $this->post(route('sand-tests.store'), ['sand_system_id' => $sand->id, 'sample_point' => 'MIXER_OUTPUT', 'values' => ['moisture_pct' => '6.2', 'permeability' => '110']])->assertSessionHasNoErrors();
        $this->assertSame('FAIL', app(Measurements::class)->sandStatus('Sheera', now()->addMinute())['state']);
        $this->post(route('sand-tests.store'), ['sand_system_id' => $sand->id, 'values' => ['moisture_pct' => '4.1']])->assertSessionHasNoErrors();
        $this->assertSame('OK', app(Measurements::class)->sandStatus('Sheera', now()->addMinute())['state']);
        $this->assertSame('NONE', app(Measurements::class)->sandStatus('Sheera', now()->addDays(2))['state']);   // older than 24 hours
        $this->post(route('sand-tests.store'), ['sand_system_id' => $sand->id, 'values' => ['moisture_pct' => '']])->assertSessionHasErrors('sand');
    }

    public function test_a_sand_test_started_from_a_run_is_linked_to_the_run_or_only_to_its_pattern(): void
    {
        $sand = \App\Models\SandSystem::where('name', 'Sheera')->firstOrFail();
        $product = \App\Models\Product::create(['code' => 'FLG-1', 'name' => 'Flange']);
        $pattern = \App\Models\Pattern::create(['code' => 'MP-7', 'name' => 'Flange plate', 'pattern_type' => 'MATCHPLATE']);
        $revision = \App\Models\PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);
        $productRevision = \App\Models\ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = \App\Models\ProductionOrder::create(['production_order_number' => 'PO-S1', 'product_id' => $product->id, 'product_revision_id' => $productRevision->id, 'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 10, 'uom' => 'piece']);
        $station = \App\Models\MouldingStation::create(['code' => 'MP-9', 'name' => 'Match plate machine 9', 'station_type' => 'MATCHPLATE_MACHINE', 'status' => 'ACTIVE']);
        $run = \App\Models\MouldingRun::create(['moulding_station_id' => $station->id, 'run_number' => 'MR-S1', 'production_order_id' => $order->id, 'pattern_revision_id' => $revision->id, 'run_date' => '2026-10-17', 'sand_system' => 'Sheera', 'status' => 'IN_PROGRESS']);

        $this->get(route('sand-tests.index', ['moulding_run_id' => $run->id]))->assertOk()->assertSee('MR-S1')->assertSee('name="moulding_run_id"', false);

        $this->post(route('sand-tests.store'), ['sand_system_id' => $sand->id, 'moulding_run_id' => $run->id, 'link_to' => 'run', 'values' => ['moisture_pct' => '4.1']])
            ->assertRedirect(route('moulding-runs.show', $run));
        $this->post(route('sand-tests.store'), ['sand_system_id' => $sand->id, 'moulding_run_id' => $run->id, 'link_to' => 'pattern', 'values' => ['moisture_pct' => '4.2']])
            ->assertRedirect(route('moulding-runs.show', $run));

        $sets = \App\Models\MeasurementSet::where('kind', 'SAND')->orderBy('created_at')->get();
        $this->assertSame([$run->id, null], $sets->pluck('moulding_run_id')->all());
        $this->assertSame([$pattern->id, $pattern->id], $sets->pluck('pattern_id')->all());
        $this->assertSame([$station->id, $station->id], $sets->pluck('moulding_station_id')->all());   // the run's station, though the form sent none
        $this->get(route('moulding-runs.show', $run))->assertSee('MP-9 — Match plate machine 9');
        $this->put(route('moulding-runs.crew', $run), ['moulding_station_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($run->fresh()->moulding_station_id);
        $this->put(route('moulding-runs.crew', $run), ['moulding_station_id' => $station->id])->assertSessionHasNoErrors();
        $this->assertSame($station->id, $run->fresh()->moulding_station_id);
        $this->get(route('moulding-runs.show', $run))->assertOk()->assertSee('Start sand test')->assertSee('This run')->assertSee('Pattern');
    }

    public function test_mould_hardness_is_recorded_per_run_from_cope_and_drag_spots_and_judged_on_lowest_and_average(): void
    {
        $sand = \App\Models\SandSystem::where('name', 'Sheera')->firstOrFail();
        $product = \App\Models\Product::create(['code' => 'FLG-2', 'name' => 'Flange']);
        $pattern = \App\Models\Pattern::create(['code' => 'MP-8', 'name' => 'Flange plate', 'pattern_type' => 'MATCHPLATE']);
        $revision = \App\Models\PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);
        $productRevision = \App\Models\ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = \App\Models\ProductionOrder::create(['production_order_number' => 'PO-H1', 'product_id' => $product->id, 'product_revision_id' => $productRevision->id, 'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 10, 'uom' => 'piece']);
        $run = \App\Models\MouldingRun::create(['run_number' => 'MR-H1', 'production_order_id' => $order->id, 'pattern_revision_id' => $revision->id, 'run_date' => '2026-10-17', 'sand_system' => 'Sheera', 'status' => 'IN_PROGRESS']);
        $this->post(route('limits.store'), ['subject_type' => 'SAND_SYSTEM', 'subject_id' => $sand->id, 'test_group' => 'MOULD', 'name' => 'Mould hardness average', 'characteristic_key' => 'hardness_b_avg', 'min_value' => 85, 'max_value' => 95, 'unit' => 'B'])->assertSessionHasNoErrors();
        $this->post(route('limits.store'), ['subject_type' => 'SAND_SYSTEM', 'subject_id' => $sand->id, 'test_group' => 'MOULD', 'name' => 'Mould hardness lowest', 'characteristic_key' => 'hardness_b_min', 'min_value' => 80, 'unit' => 'B'])->assertSessionHasNoErrors();

        $this->post(route('moulding-runs.mould-tests.store', $run), ['cope' => ['90', '88', '', '', ''], 'drag' => ['92', '', '', '', '']])->assertSessionHasNoErrors()->assertRedirect(route('moulding-runs.show', $run));
        $good = \App\Models\MeasurementSet::where('kind', 'MOULD')->sole();
        $this->assertSame('PASS', $good->result);
        $this->assertSame([$run->id, $pattern->id, $sand->id], [$good->moulding_run_id, $good->pattern_id, $good->sand_system_id]);
        $by = $good->measurements->keyBy('characteristic_key');
        $this->assertEquals([88, 90], [(float) $by['hardness_b_min']->value, (float) $by['hardness_b_avg']->value]);
        $this->assertEquals([90, 88, 92], [(float) $by['cope_spot_1']->value, (float) $by['cope_spot_2']->value, (float) $by['drag_spot_1']->value]);

        $this->post(route('moulding-runs.mould-tests.store', $run), ['cope' => ['90', '70'], 'drag' => ['92']])->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\MeasurementSet::where('kind', 'MOULD')->where('result', 'FAIL')->count());   // lowest spot 70 is under 80 and the average 84 under 85

        $this->post(route('moulding-runs.mould-tests.store', $run), ['cope' => ['', ''], 'drag' => []])->assertSessionHasErrors('mould_test');
        $this->post(route('moulding-runs.mould-tests.store', $run), ['mould_assembly_id' => (string) \Illuminate\Support\Str::uuid(), 'cope' => ['90']])->assertSessionHasErrors('mould_test');
        $this->assertSame(2, \App\Models\MeasurementSet::where('kind', 'MOULD')->count());

        $this->get(route('moulding-runs.show', $run))->assertOk()->assertSee('Mould hardness (B scale)')->assertSee('90 · 88');
        $this->get(route('sand-tests.index'))->assertOk()->assertSee('Recent mould hardness tests')->assertSee('MR-H1');
    }
}
