<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\ThermalAnalysisTest;
use App\Services\Thermal\ThermalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThermalVoidTest extends TestCase
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

    public function test_voided_test_is_replaced_by_the_corrected_one(): void
    {
        $heat = $this->heat();
        $svc = app(ThermalService::class);
        $wrong = $svc->recordManual($heat, ['tl_c' => 1175, 'solidus_c' => 1120], ['cup_type' => 'TELLURIUM']);

        $this->post(route('thermal.void', $wrong), ['void_reason' => 'TL keyed wrongly'])->assertRedirect(route('thermal.create', ['heat_id' => $heat->id]));

        $this->assertTrue($wrong->fresh()->isVoided());
        $this->assertNull($svc->latestTest($heat));

        $right = $svc->recordManual($heat, ['tl_c' => 1180, 'solidus_c' => 1120], ['cup_type' => 'TELLURIUM']);
        $this->assertTrue($svc->latestTest($heat)->is($right));
        $this->get(route('thermal.show', $wrong))->assertOk()->assertSee('TL keyed wrongly');
        $this->assertSame(2, ThermalAnalysisTest::withoutGlobalScopes()->count());
    }

    public function test_a_reason_is_required(): void
    {
        $t = app(ThermalService::class)->recordManual($this->heat(), ['tl_c' => 1175, 'solidus_c' => 1120], ['cup_type' => 'TELLURIUM']);
        $this->post(route('thermal.void', $t), ['void_reason' => ''])->assertSessionHasErrors('void_reason');
        $this->assertFalse($t->fresh()->isVoided());
    }

    public function test_ce_keyed_from_the_analyser_is_stored_in_place_of_the_one_worked_out_from_tl(): void
    {
        $t = app(ThermalService::class)->recordManual($this->heat(), ['tl_c' => 1175, 'solidus_c' => 1120, 'silicon' => 2.4, 'ce' => 4.52], ['cup_type' => 'TELLURIUM']);

        $this->assertSame('METER', $t->ce_source);
        $this->assertEquals(4.52, (float) $t->ce_from_tl);
        $this->assertEquals(round(4.52 - 2.4 / 3, 4), (float) $t->carbon_from_tl);
    }

    public function test_ce_is_marked_calculated_when_the_meter_gave_none(): void
    {
        $t = app(ThermalService::class)->recordManual($this->heat(), ['tl_c' => 1175, 'solidus_c' => 1120], ['cup_type' => 'TELLURIUM']);

        $this->assertSame('CALCULATED', $t->ce_source);
        $this->get(route('thermal.show', $t))->assertSee('calculated from TL');
    }

    public function test_cup_times_and_sampling_details_are_stored_per_cup(): void
    {
        $heat = $this->heat();
        $this->post(route('thermal.store'), [
            'heat_id' => $heat->id, 'stage' => 'POST_TREATMENT', 'sample_point' => 'TREATMENT_LADLE',
            'white' => ['tl_c' => 1175, 'solidus_c' => 1120, 'taken_at' => '2026-10-20 09:00', 'test_started_at' => '2026-10-20 09:01', 'test_finished_at' => '2026-10-20 09:04', 'cup_batch' => 'LOT-7'],
            'grey' => ['tl_c' => 1190, 'te_min_c' => 1146, 'te_max_c' => 1149, 'taken_at' => '2026-10-20 09:00'],
        ])->assertRedirect();

        $white = ThermalAnalysisTest::where('cup_type', 'TELLURIUM')->firstOrFail();
        $this->assertSame('LOT-7', $white->cup_batch);
        $this->assertSame('TREATMENT_LADLE', $white->sample_point);
        $this->assertSame(180, $white->testDurationSeconds());
        $this->assertSame('09:00', $white->taken_at->format('H:i'));
        $this->assertNull(ThermalAnalysisTest::where('cup_type', 'STABLE')->first()->test_started_at);
    }

    public function test_finish_cannot_be_before_start(): void
    {
        $this->post(route('thermal.store'), [
            'heat_id' => $this->heat()->id, 'stage' => 'POST_TREATMENT',
            'white' => ['tl_c' => 1175, 'solidus_c' => 1120, 'test_started_at' => '2026-10-20 09:05', 'test_finished_at' => '2026-10-20 09:01'],
        ])->assertSessionHasErrors('white.test_finished_at');
    }
}
