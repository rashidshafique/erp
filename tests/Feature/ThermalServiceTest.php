<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\ThermalAnalysisTest;
use App\Models\ThermalWindow;
use App\Services\Thermal\ThermalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CurveSimulator;
use Tests\TestCase;

class ThermalServiceTest extends TestCase
{
    use RefreshDatabase;

    private function heat(string $gradeCode = 'EN-GJS-500-7'): Heat
    {
        $f = Furnace::create(['code' => 'IF-'.uniqid(), 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create([
            'heat_number' => 'H-'.uniqid(), 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED',
            'iron_grade_id' => IronGrade::where('code', $gradeCode)->value('id'),
        ]);
    }

    private function csv(array $curve): string
    {
        return "time_s,temp_c\n".implode("\n", array_map(fn ($r) => $r[0].','.$r[1], $curve));
    }

    public function test_default_relation_converts_tl_to_ce(): void
    {
        $svc = app(ThermalService::class);
        // 1669 - 124.5 * 4.4 = 1121.2
        $this->assertEqualsWithDelta(4.4, $svc->ceFromTl(1121.2), 0.01);
    }

    public function test_pass_and_fail_against_a_window(): void
    {
        $heat = $this->heat();
        ThermalWindow::create(['iron_grade_id' => $heat->iron_grade_id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1140, 'recal_max_c' => 6]);
        $svc = app(ThermalService::class);

        $good = $svc->record($heat, $this->csv(CurveSimulator::make(1195, 1150, 4.0)['curve']), ['taken_by' => 'QC']);
        $this->assertSame('PASS', $good->result, json_encode($good->findings));

        $bad = $svc->record($heat, $this->csv(CurveSimulator::make(1195, 1150, 14.0)['curve']), []);
        $this->assertSame('FAIL', $bad->result);
        $this->assertNotEmpty($bad->findings);
    }

    public function test_no_window_means_not_judged(): void
    {
        $t = app(ThermalService::class)->record($this->heat(), $this->csv(CurveSimulator::make()['curve']), []);
        $this->assertSame('NOT_JUDGED', $t->result);
    }

    public function test_inoculant_advice_is_capped_and_only_when_needed(): void
    {
        $heat = $this->heat();
        ThermalWindow::create(['iron_grade_id' => $heat->iron_grade_id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1150]);
        $svc = app(ThermalService::class);

        $low = $svc->inoculantAdvice(['te_min_c' => 1146.0], $heat->ironGrade);
        $this->assertTrue($low['needed']);
        $this->assertEqualsWithDelta(0.07, $low['suggest_pct'], 0.01);

        $huge = $svc->inoculantAdvice(['te_min_c' => 1100.0], $heat->ironGrade);
        $this->assertTrue($huge['capped']);
        $this->assertSame(0.4, $huge['suggest_pct']);

        $this->assertFalse($svc->inoculantAdvice(['te_min_c' => 1152.0], $heat->ironGrade)['needed']);
    }

    public function test_calibration_fits_a_line_and_changes_the_conversion(): void
    {
        $svc = app(ThermalService::class);
        $truthA = 1650.0;
        $truthB = 120.0;
        foreach (range(0, 9) as $i) {
            $heat = $this->heat();
            $ce = 4.10 + $i * 0.05;
            \DB::table('heat_chemistry_readings')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(), 'heat_id' => $heat->id, 'reading_number' => 1,
                'carbon_equivalent' => $ce, 'created_at' => now(), 'updated_at' => now(),
            ]);
            ThermalAnalysisTest::create([
                'heat_id' => $heat->id, 'taken_at' => now(), 'curve' => [[0, 1300]], 'tl_c' => $truthA - $truthB * $ce + (($i % 3) - 1) * 0.3,
            ]);
        }
        $cal = $svc->calibrate();
        $this->assertEqualsWithDelta($truthB, (float) $cal->tl_slope_c_per_ce, 3.0);
        $this->assertEqualsWithDelta($truthA, (float) $cal->tl_intercept_c, 15.0);
        $this->assertSame(10, $cal->sample_count);
        $this->assertTrue($svc->relation()['calibrated']);
    }

    public function test_calibration_refuses_too_little_data(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ThermalService::class)->calibrate();
    }

    public function test_parse_accepts_header_and_mixed_separators(): void
    {
        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $rows[] = $i % 2 ? "$i;".(1300 - $i) : "$i\t".(1300 - $i);
        }
        $parsed = app(ThermalService::class)->parseCurve("time temp\n".implode("\n", $rows));
        $this->assertCount(50, $parsed);
    }
}
