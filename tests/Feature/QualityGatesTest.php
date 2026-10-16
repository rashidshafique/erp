<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\ThermalAnalysisTest;
use App\Models\ThermalWindow;
use App\Services\QualityGates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityGatesTest extends TestCase
{
    use RefreshDatabase;

    private function heat(bool $withGrade = true): Heat
    {
        $f = Furnace::create(['code' => 'IF'.uniqid(), 'name' => 'x', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED',
            'iron_grade_id' => $withGrade ? IronGrade::where('code', 'EN-GJL-250')->value('id') : null]);
    }

    private function test(Heat $h, string $result, int $minutesAgo = 5): void
    {
        ThermalAnalysisTest::create(['heat_id' => $h->id, 'taken_at' => now()->subMinutes($minutesAgo), 'curve' => [[0, 1300]], 'result' => $result, 'sample_reference' => 'S1', 'findings' => $result === 'FAIL' ? ['TEmin low.'] : []]);
    }

    private function chem(Heat $h, float $c, float $si): void
    {
        \DB::table('heat_chemistry_readings')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'heat_id' => $h->id, 'reading_number' => 1,
            'carbon_percent' => $c, 'silicon_percent' => $si, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_legacy_heat_without_grade_is_not_gated(): void
    {
        $this->assertTrue(app(QualityGates::class)->pourGate($this->heat(false))['ok']);
    }

    public function test_grade_heat_needs_chemistry_and_a_recent_passing_thermal_test(): void
    {
        $h = $this->heat();
        ThermalWindow::create(['iron_grade_id' => $h->iron_grade_id, 'cup_type' => 'STABLE', 'te_min_floor_c' => 1130]);
        $g = app(QualityGates::class);

        $this->assertFalse($g->pourGate($h)['ok']);                       // nothing recorded
        $this->chem($h, 3.2, 2.0);
        $this->assertFalse($g->pourGate($h->fresh())['ok']);              // chemistry fine, no thermal test

        $this->test($h, 'FAIL');
        $this->assertFalse($g->pourGate($h->fresh())['ok']);

        $this->test($h, 'PASS', 60);                                      // newest by taken_at is the FAIL; add a fresh pass
        $h->thermalTests()->delete();
        $this->test($h, 'PASS', 60);
        $r = $g->pourGate($h->fresh());
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('older than', implode(' ', $r['reasons']));

        $h->thermalTests()->delete();
        $this->test($h, 'PASS', 5);
        $this->assertTrue($g->pourGate($h->fresh())['ok']);
    }

    public function test_chemistry_outside_the_grade_window_blocks(): void
    {
        $h = $this->heat();
        $this->chem($h, 3.9, 2.0);       // carbon above the GJL-250 maximum
        $r = app(QualityGates::class)->pourGate($h);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('C 3.9', implode(' ', $r['reasons']));
    }

    public function test_gate_can_be_switched_off(): void
    {
        config(['foundry.enforce_thermal_gate' => false]);
        $this->assertTrue(app(QualityGates::class)->pourGate($this->heat())['ok']);
    }
}
