<?php

namespace Tests\Unit;

use App\Services\Thermal\CurveAnalyser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\CurveSimulator;

class CurveAnalyserTest extends TestCase
{
    public function test_finds_characteristic_points_on_a_clean_curve(): void
    {
        $sim = CurveSimulator::make(1195.0, 1150.0, 8.0);
        $r = (new CurveAnalyser())->analyse($sim['curve']);

        $this->assertNotNull($r['tl_c']);
        $this->assertEqualsWithDelta(1195.0, $r['tl_c'], 8.0);
        $this->assertEqualsWithDelta($sim['truth']['te_min'], $r['te_min_c'], 2.0);
        $this->assertEqualsWithDelta($sim['truth']['te_max'], $r['te_max_c'], 2.0);
        $this->assertGreaterThan($r['te_max_c'], $r['tl_c']);
        $this->assertGreaterThanOrEqual(0.0, $r['recalescence_c']);
        $this->assertNotNull($r['solidus_c']);
        $this->assertLessThanOrEqual($r['te_max_c'], $r['solidus_c']);
    }

    public function test_deeper_undercooling_gives_lower_temin_and_larger_recalescence(): void
    {
        $a = new CurveAnalyser();
        $shallow = $a->analyse(CurveSimulator::make(1195.0, 1150.0, 4.0)['curve']);
        $deep = $a->analyse(CurveSimulator::make(1195.0, 1150.0, 14.0)['curve']);

        $this->assertLessThan($shallow['te_min_c'], $deep['te_min_c']);
        $this->assertGreaterThan($shallow['recalescence_c'], $deep['recalescence_c']);
    }

    public function test_higher_liquidus_curve_reports_higher_tl(): void
    {
        $a = new CurveAnalyser();
        $lowTl = $a->analyse(CurveSimulator::make(1170.0, 1150.0, 8.0)['curve']);
        $highTl = $a->analyse(CurveSimulator::make(1210.0, 1150.0, 8.0)['curve']);

        $this->assertGreaterThan($lowTl['tl_c'], $highTl['tl_c']);
    }

    public function test_tolerates_instrument_noise(): void
    {
        $sim = CurveSimulator::make(1195.0, 1150.0, 8.0, 0.2, 0.4, 7);
        $r = (new CurveAnalyser())->analyse($sim['curve']);

        $this->assertEqualsWithDelta($sim['truth']['te_min'], $r['te_min_c'], 3.0);
        $this->assertNotNull($r['tl_c']);
        $this->assertEqualsWithDelta(1195.0, $r['tl_c'], 12.0);
    }

    public function test_rejects_short_or_unusable_curves(): void
    {
        $a = new CurveAnalyser();

        $this->expectException(InvalidArgumentException::class);
        $a->analyse([[0, 1300], [1, 1290], [2, 1280]]);
    }

    public function test_rejects_a_curve_that_never_reaches_liquidus_range(): void
    {
        $curve = [];
        for ($i = 0; $i < 100; $i++) {
            $curve[] = [$i, 900 - $i];
        }
        $this->expectException(InvalidArgumentException::class);
        (new CurveAnalyser())->analyse($curve);
    }

    public function test_handles_unsorted_rows_and_duplicate_times(): void
    {
        $sim = CurveSimulator::make();
        $rows = $sim['curve'];
        $rows[] = $rows[10];
        shuffle($rows);

        $r = (new CurveAnalyser())->analyse($rows);
        $this->assertEqualsWithDelta($sim['truth']['te_min'], $r['te_min_c'], 2.0);
    }
}
