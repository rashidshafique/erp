<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\ThermalAnalysisTest;
use App\Models\ThermalWindow;
use App\Services\Thermal\ThermalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Illuminate\Http\UploadedFile;
use Tests\Support\CurveSimulator;
use Tests\TestCase;

class ManualThermalEntryTest extends TestCase
{
    use RefreshDatabase;

    private function heat(): Heat
    {
        $f = Furnace::create(['code' => 'IF-'.uniqid(), 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create([
            'heat_number' => 'H-'.uniqid(), 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED',
            'iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id'),
        ]);
    }

    public function test_readings_without_a_curve_are_judged_and_saved(): void
    {
        $heat = $this->heat();
        ThermalWindow::create(['iron_grade_id' => $heat->iron_grade_id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1140, 'recal_max_c' => 6]);

        $good = app(ThermalService::class)->recordManual($heat, ['tl_c' => 1190, 'te_min_c' => 1146, 'te_max_c' => 1149], ['cup_type' => 'STABLE']);
        $this->assertSame('MANUAL', $good->entry_mode);
        $this->assertSame([], $good->curve);
        $this->assertEquals(3.0, (float) $good->recalescence_c);
        $this->assertSame('PASS', $good->result);
        $this->assertNotNull($good->ce_from_tl);

        $bad = app(ThermalService::class)->recordManual($heat, ['tl_c' => 1190, 'te_min_c' => 1132, 'te_max_c' => 1150], ['cup_type' => 'STABLE']);
        $this->assertSame('FAIL', $bad->result);
    }

    public function test_tellurium_cup_needs_only_tl_and_solidus(): void
    {
        $t = app(ThermalService::class)->recordManual($this->heat(), ['tl_c' => 1175, 'solidus_c' => 1120], ['cup_type' => 'TELLURIUM']);
        $this->assertSame('TELLURIUM', $t->cup_type);
        $this->assertNotNull($t->ce_from_tl);
    }

    public function test_impossible_readings_are_refused(): void
    {
        $svc = app(ThermalService::class);
        foreach ([
            [],
            ['tl_c' => 1190, 'te_min_c' => 1150, 'te_max_c' => 1140],
            ['tl_c' => 1130, 'te_min_c' => 1140, 'te_max_c' => 1148],
        ] as $values) {
            try {
                $svc->recordManual($this->heat(), $values, []);
                $this->fail('Should have been refused: '.json_encode($values));
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_form_saves_manual_readings_and_pages_render(): void
    {
        $heat = $this->heat();
        $this->get(route('thermal.create'))->assertOk()->assertSee('White cup', false)->assertSee('Grey cup', false);

        $r = $this->post(route('thermal.store'), [
            'heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'cup_type' => 'STABLE', 'entry_mode' => 'MANUAL',
            'tl_c' => 1188.5, 'te_min_c' => 1145, 'te_max_c' => 1148,
        ]);
        $test = ThermalAnalysisTest::firstOrFail();
        $r->assertRedirect(route('thermal.show', $test));
        $this->get(route('thermal.show', $test))->assertOk()->assertSee('no cooling curve to show');
        $this->get(route('thermal.index'))->assertOk()->assertSee('keyed in');

        $this->post(route('thermal.store'), ['heat_id' => $heat->id, 'stage' => 'BASE', 'cup_type' => 'STABLE', 'entry_mode' => 'MANUAL'])
            ->assertSessionHasErrors('curve_text');
    }

    public function test_white_and_grey_cup_saved_together_with_carbon_silicon_and_peak(): void
    {
        $heat = $this->heat();
        ThermalWindow::create(['iron_grade_id' => $heat->iron_grade_id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1140, 'recal_max_c' => 6]);

        $this->post(route('thermal.store'), [
            'heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'sample_reference' => 'S-7',
            'white' => ['carbon' => 3.62, 'silicon' => 2.4, 'tl_c' => 1182, 'solidus_c' => 1121, 'peak_temp_c' => 1385],
            'grey' => ['tl_c' => 1184, 'solidus_c' => 1122, 'te_min_c' => 1146, 'te_max_c' => 1149, 'peak_temp_c' => 1388],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, ThermalAnalysisTest::count());
        $white = ThermalAnalysisTest::where('cup_type', 'TELLURIUM')->firstOrFail();
        $grey = ThermalAnalysisTest::where('cup_type', 'STABLE')->firstOrFail();
        $this->assertNotNull($white->sample_group_id);
        $this->assertSame($white->sample_group_id, $grey->sample_group_id);
        $this->assertEquals(3.62, (float) $white->carbon_reported_pct);
        $this->assertEquals(2.4, (float) $white->silicon_used);
        $this->assertEquals(1385.0, (float) $white->peak_temp_c);
        $this->assertEquals(2.4, (float) $grey->silicon_used, 'grey cup borrows the white cup silicon');
        $this->assertSame('PASS', $grey->result);
        $this->assertNull($white->te_min_c);

        $this->get(route('thermal.show', $white))->assertOk()->assertSee('Other cup from this sample');
        $this->get(route('thermal.index'))->assertOk()->assertSee('White');
    }

    public function test_an_empty_card_is_ignored_and_nothing_at_all_is_refused(): void
    {
        $heat = $this->heat();
        $this->post(route('thermal.store'), ['heat_id' => $heat->id, 'stage' => 'BASE', 'grey' => ['tl_c' => 1184, 'te_min_c' => 1146, 'te_max_c' => 1149]])->assertSessionHasNoErrors();
        $this->assertSame(1, ThermalAnalysisTest::count());
        $this->assertSame('STABLE', ThermalAnalysisTest::first()->cup_type);

        $this->post(route('thermal.store'), ['heat_id' => $heat->id, 'stage' => 'BASE', 'white' => ['silicon' => ''], 'grey' => []])->assertSessionHasErrors('curve_text');
    }

    public function test_curve_and_keyed_values_combine_and_a_big_gap_is_flagged(): void
    {
        $heat = $this->heat();
        $curve = CurveSimulator::make(1195, 1150, 4.0)['curve'];
        $csv = "time_s,temp_c\n".implode("\n", array_map(fn ($r) => $r[0].','.$r[1], $curve));

        $t = app(ThermalService::class)->recordCup($heat, 'STABLE', ['tl_c' => 1210], $csv);
        $this->assertSame('CURVE', $t->entry_mode);
        $this->assertNotEmpty($t->curve);
        $this->assertEquals(1210.0, (float) $t->tl_c, 'the keyed TL wins');
        $this->assertNotNull($t->peak_temp_c, 'peak comes from the curve when not keyed');
        $this->assertStringContainsString('differs from the curve', implode(' ', $t->findings));
    }

    public function test_white_cup_curve_gives_only_tl_ts_and_peak(): void
    {
        $curve = CurveSimulator::make(1195, 1150, 4.0)['curve'];
        $csv = "time_s,temp_c\n".implode("\n", array_map(fn ($r) => $r[0].','.$r[1], $curve));
        $t = app(ThermalService::class)->recordCup($this->heat(), 'TELLURIUM', [], $csv);
        $this->assertNull($t->te_min_c);
        $this->assertNull($t->te_max_c);
        $this->assertNull($t->recalescence_c);
        $this->assertNotNull($t->tl_c);
        $this->assertNotNull($t->peak_temp_c);
    }

    public function test_pour_gate_ignores_an_unjudged_white_cup_when_the_grey_cup_passes_and_fails_if_any_fails(): void
    {
        config(['foundry.enforce_thermal_gate' => true]);
        $heat = $this->heat();
        ThermalWindow::create(['iron_grade_id' => $heat->iron_grade_id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1140, 'recal_max_c' => 6]);

        $this->post(route('thermal.store'), ['heat_id' => $heat->id, 'stage' => 'PRE_POUR', 'white' => ['tl_c' => 1182, 'solidus_c' => 1121], 'grey' => ['tl_c' => 1184, 'te_min_c' => 1146, 'te_max_c' => 1149]]);
        $gate = fn () => collect(app(\App\Services\QualityGates::class)->pourGate($heat->fresh())['checks'] ?? [])->firstWhere('label', 'Thermal analysis');
        $this->assertTrue($gate()['ok'], json_encode($gate()));

        $this->post(route('thermal.store'), ['heat_id' => $heat->id, 'stage' => 'PRE_POUR', 'white' => ['tl_c' => 1182, 'solidus_c' => 1121], 'grey' => ['tl_c' => 1184, 'te_min_c' => 1130, 'te_max_c' => 1149]]);
        $this->assertFalse($gate()['ok']);
    }

    public function test_carbon_and_silicon_keep_up_to_four_decimal_places_or_none(): void
    {
        $heat = $this->heat();

        $this->post(route('thermal.store'), [
            'heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'sample_reference' => 'S-4DP',
            'white' => ['carbon' => '3.6213', 'silicon' => '2.4057', 'tl_c' => 1182, 'solidus_c' => 1121],
            'grey' => ['tl_c' => 1184, 'solidus_c' => 1122, 'te_min_c' => 1146, 'te_max_c' => 1149],
        ])->assertSessionHasNoErrors();

        $white = ThermalAnalysisTest::where('cup_type', 'TELLURIUM')->firstOrFail();
        $this->assertSame(['3.6213', '2.4057'], [(string) $white->carbon_reported_pct, (string) $white->silicon_used]);
        $this->get(route('thermal.show', $white))->assertOk()->assertSee('3.6213 / 2.4057');

        $this->post(route('thermal.store'), [
            'heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'sample_reference' => 'S-WHOLE',
            'white' => ['carbon' => '4', 'silicon' => '2', 'tl_c' => 1182, 'solidus_c' => 1121],
        ])->assertSessionHasNoErrors();
        $this->get(route('thermal.index'))->assertOk()->assertSee('S-WHOLE');
        $this->assertEquals([4.0, 2.0], [(float) ThermalAnalysisTest::where('sample_reference', 'S-WHOLE')->firstOrFail()->carbon_reported_pct, (float) ThermalAnalysisTest::where('sample_reference', 'S-WHOLE')->firstOrFail()->silicon_used]);
    }

    public function test_more_than_four_decimal_places_are_refused(): void
    {
        $heat = $this->heat();

        $this->post(route('thermal.store'), [
            'heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'white' => ['carbon' => '3.62134', 'silicon' => '2.40571', 'tl_c' => 1182, 'solidus_c' => 1121],
        ])->assertSessionHasErrors(['white.carbon', 'white.silicon']);

        $this->assertSame(0, ThermalAnalysisTest::count());
    }

    public function test_the_entry_form_takes_any_decimals_in_the_browser(): void
    {
        $this->get(route('thermal.create'))->assertOk()
            ->assertSee('name="white[carbon]" type="number" step="any"', false)->assertSee('name="grey[silicon]" type="number" step="any"', false);
    }

    public function test_the_test_page_title_is_the_heat_id_without_a_heat_prefix_and_opens_the_heat(): void
    {
        $heat = $this->heat();
        $this->post(route('thermal.store'), ['heat_id' => $heat->id, 'stage' => 'BASE', 'cup_type' => 'STABLE', 'tl_c' => 1188.5, 'te_min_c' => 1145, 'te_max_c' => 1148]);
        $test = ThermalAnalysisTest::firstOrFail();

        $page = $this->get(route('thermal.show', $test))->assertOk()
            ->assertSee('<h1>Thermal test — <a href="'.route('heats.show', $heat).'" title="Open the heat">'.$heat->heat_number.'</a>', false)
            ->assertSee('<title>Thermal test · '.$heat->heat_number.' — Foundry ERP</title>', false);
        $this->assertStringNotContainsString('heat '.$heat->heat_number, $page->getContent());
    }
}
