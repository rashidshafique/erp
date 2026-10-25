<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\ThermalAnalysisTest;
use App\Models\ThermalWindow;
use App\Services\QualityGates;
use App\Services\Thermal\ThermalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ThermalWhiteCupWindowTest extends TestCase
{
    use RefreshDatabase;

    private function grade(): IronGrade
    {
        return IronGrade::where('code', 'EN-GJL-200')->firstOrFail();
    }

    private function heat(): Heat
    {
        $furnace = Furnace::create(['code' => 'CU-'.uniqid(), 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'rated_capacity_kg' => 5000, 'nominal_melting_rate_kg_per_hour' => 3000]);

        return Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'MELTING', 'iron_grade_id' => $this->grade()->id]);
    }

    private function whiteWindow(array $limits): ThermalWindow
    {
        return ThermalWindow::create(['iron_grade_id' => $this->grade()->id, 'cup_type' => 'TELLURIUM'] + $limits);
    }

    public function test_the_grade_page_offers_a_white_cup_window_starting_from_the_grade_ce_limits(): void
    {
        $grade = $this->grade();

        $this->get(route('iron-grades.show', $grade))->assertOk()
            ->assertSee('white / tellurium cup')->assertSee('name="ce_min" type="number" step="any" value="'.$grade->ce_min.'"', false);
    }

    public function test_a_white_cup_window_is_saved_and_never_keeps_eutectic_limits(): void
    {
        $grade = $this->grade();

        $this->put(route('iron-grades.window.white', $grade), ['tl_min_c' => 1150, 'tl_max_c' => 1200, 'ce_min' => 3.9, 'ce_max' => 4.4, 'solidus_min_c' => 1100, 'te_min_floor_c' => 1140, 'recal_max_c' => 6])->assertSessionHasNoErrors();

        $window = ThermalWindow::where('iron_grade_id', $grade->id)->where('cup_type', 'TELLURIUM')->firstOrFail();
        $this->assertEquals([1150, 1200, 3.9, 4.4, 1100], [(float) $window->tl_min_c, (float) $window->tl_max_c, (float) $window->ce_min, (float) $window->ce_max, (float) $window->solidus_min_c]);
        $this->assertNull($window->te_min_floor_c);
        $this->assertNull($window->recal_max_c);
        $this->assertNull(ThermalWindow::where('iron_grade_id', $grade->id)->where('cup_type', 'STABLE')->first());
    }

    public function test_the_white_window_rejects_a_max_below_its_min(): void
    {
        $this->put(route('iron-grades.window.white', $this->grade()), ['ce_min' => 4.4, 'ce_max' => 3.9])->assertSessionHasErrors('ce_max');
    }

    public function test_a_white_cup_is_judged_on_tl_ce_and_solidus(): void
    {
        $this->whiteWindow(['tl_min_c' => 1150, 'tl_max_c' => 1200, 'solidus_min_c' => 1100]);
        $heat = $this->heat();
        $svc = app(ThermalService::class);

        $good = $svc->recordCup($heat, 'TELLURIUM', ['tl_c' => 1180, 'solidus_c' => 1120], null);
        $this->assertSame('PASS', $good->result);

        $hot = $svc->recordCup($heat, 'TELLURIUM', ['tl_c' => 1210, 'solidus_c' => 1120], null);
        $this->assertSame('FAIL', $hot->result);
        $this->assertStringContainsString('TL 1210', implode(' ', $hot->findings));

        $low = $svc->recordCup($heat, 'TELLURIUM', ['tl_c' => 1180, 'solidus_c' => 1090], null);
        $this->assertSame('FAIL', $low->result);
    }

    public function test_a_white_cup_is_never_failed_for_the_eutectic_values_it_cannot_have(): void
    {
        $this->whiteWindow(['tl_min_c' => 1150, 'te_min_floor_c' => 1140, 'recal_min_c' => 2, 'recal_max_c' => 6]);

        $test = app(ThermalService::class)->recordCup($this->heat(), 'TELLURIUM', ['tl_c' => 1180, 'solidus_c' => 1120], null);

        $this->assertSame('PASS', $test->result, implode(' ', $test->findings ?? []));
    }

    public function test_a_white_cup_with_no_window_is_still_not_judged(): void
    {
        $test = app(ThermalService::class)->recordCup($this->heat(), 'TELLURIUM', ['tl_c' => 1180, 'solidus_c' => 1120], null);

        $this->assertSame('NOT_JUDGED', $test->result);
    }

    public function test_ce_is_judged_on_the_white_cup_when_the_sample_has_one(): void
    {
        $svc = app(ThermalService::class);
        $whiteCe = $svc->ceFromTl(1180, 'TELLURIUM');
        $greyCe = $svc->ceFromTl(1184, 'STABLE');
        $this->assertNotEquals($whiteCe, $greyCe);
        $grade = $this->grade();
        ThermalWindow::create(['iron_grade_id' => $grade->id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1100, 'ce_min' => $greyCe + 1, 'ce_max' => $greyCe + 2]);
        $this->whiteWindow(['ce_min' => $whiteCe - 0.5, 'ce_max' => $whiteCe + 0.5]);
        $heat = $this->heat();
        $group = (string) Str::uuid();

        $white = $svc->recordCup($heat, 'TELLURIUM', ['tl_c' => 1180, 'solidus_c' => 1120], null, $group);
        $grey = $svc->recordCup($heat, 'STABLE', ['tl_c' => 1184, 'te_min_c' => 1146, 'te_max_c' => 1149, 'solidus_c' => 1122], null, $group);

        $this->assertSame('PASS', $white->result);
        $this->assertSame('PASS', $grey->result, 'the grey cup CE limit is left to the white cup: '.implode(' ', $grey->findings ?? []));
    }

    public function test_a_grey_cup_on_its_own_still_has_its_ce_judged(): void
    {
        $svc = app(ThermalService::class);
        $greyCe = $svc->ceFromTl(1184, 'STABLE');
        ThermalWindow::create(['iron_grade_id' => $this->grade()->id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1100, 'ce_min' => $greyCe + 1, 'ce_max' => $greyCe + 2]);
        $this->whiteWindow(['ce_min' => 3.0, 'ce_max' => 5.0]);

        $alone = $svc->recordCup($this->heat(), 'STABLE', ['tl_c' => 1184, 'te_min_c' => 1146, 'te_max_c' => 1149, 'solidus_c' => 1122], null);

        $this->assertSame('FAIL', $alone->result);
        $this->assertStringContainsString('CE from TL', implode(' ', $alone->findings));
    }

    public function test_a_failing_white_cup_fails_the_pour_gate_even_when_the_grey_cup_passes(): void
    {
        ThermalWindow::create(['iron_grade_id' => $this->grade()->id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1100]);
        $this->whiteWindow(['tl_min_c' => 1150, 'tl_max_c' => 1200]);
        $heat = $this->heat();
        $svc = app(ThermalService::class);
        $group = (string) Str::uuid();
        $svc->recordCup($heat, 'TELLURIUM', ['tl_c' => 1250, 'solidus_c' => 1120], null, $group);
        $svc->recordCup($heat, 'STABLE', ['tl_c' => 1184, 'te_min_c' => 1146, 'te_max_c' => 1149, 'solidus_c' => 1122], null, $group);

        $gate = app(QualityGates::class)->pourGate($heat->fresh());
        $thermal = collect($gate['checks'])->firstWhere('label', 'Thermal analysis');

        $this->assertFalse($thermal['ok']);
        $this->assertSame(2, ThermalAnalysisTest::where('heat_id', $heat->id)->count());
    }

    public function test_a_grey_cup_takes_ce_carbon_and_silicon_from_the_white_cup_of_its_sample(): void
    {
        $svc = app(ThermalService::class);
        $heat = $this->heat();
        $group = (string) Str::uuid();

        $white = $svc->recordCup($heat, 'TELLURIUM', ['tl_c' => 1180, 'solidus_c' => 1120, 'carbon' => '3.2105', 'silicon' => '2.0451'], null, $group);
        $grey = $svc->recordCup($heat, 'STABLE', ['tl_c' => 1190, 'te_min_c' => 1146, 'te_max_c' => 1149, 'solidus_c' => 1122, 'carbon' => '3.9', 'silicon' => '1.5'], null, $group);

        $this->assertEquals((float) $white->ce_from_tl, (float) $grey->ce_from_tl);
        $this->assertEquals((float) $white->carbon_from_tl, (float) $grey->carbon_from_tl);
        $this->assertEquals([3.2105, 2.0451], [(float) $grey->carbon_reported_pct, (float) $grey->silicon_used]);
        $this->assertStringContainsString('taken from the white cup', implode(' ', $grey->findings));
        $this->assertNotEquals($svc->ceFromTl(1190, 'STABLE'), (float) $grey->ce_from_tl);
    }

    public function test_the_grey_cup_is_judged_on_the_white_cup_ce_even_when_the_white_window_has_no_ce_limit(): void
    {
        $svc = app(ThermalService::class);
        $whiteCe = $svc->ceFromTl(1180, 'TELLURIUM');
        ThermalWindow::create(['iron_grade_id' => $this->grade()->id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1100, 'ce_min' => $whiteCe - 0.1, 'ce_max' => $whiteCe + 0.1]);
        $heat = $this->heat();
        $group = (string) Str::uuid();

        $svc->recordCup($heat, 'TELLURIUM', ['tl_c' => 1180, 'solidus_c' => 1120], null, $group);
        $pair = $svc->recordCup($heat, 'STABLE', ['tl_c' => 1215, 'te_min_c' => 1146, 'te_max_c' => 1149, 'solidus_c' => 1122], null, $group);
        $alone = $svc->recordCup($heat, 'STABLE', ['tl_c' => 1215, 'te_min_c' => 1146, 'te_max_c' => 1149, 'solidus_c' => 1122], null);

        $this->assertSame('PASS', $pair->result, implode(' ', $pair->findings ?? []));
        $this->assertSame('FAIL', $alone->result);
    }

    public function test_a_grey_cup_keeps_its_own_values_when_the_white_cup_has_none(): void
    {
        $svc = app(ThermalService::class);
        $heat = $this->heat();
        $group = (string) Str::uuid();

        $svc->recordCup($heat, 'TELLURIUM', ['solidus_c' => 1120], null, $group);
        $grey = $svc->recordCup($heat, 'STABLE', ['tl_c' => 1184, 'te_min_c' => 1146, 'te_max_c' => 1149, 'solidus_c' => 1122, 'carbon' => '3.4', 'silicon' => '2.1'], null, $group);

        $this->assertEquals([3.4, 2.1, $svc->ceFromTl(1184, 'STABLE')], [(float) $grey->carbon_reported_pct, (float) $grey->silicon_used, (float) $grey->ce_from_tl]);
    }
}
