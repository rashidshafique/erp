<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatChemistryReading;
use App\Models\IronGrade;
use App\Services\Quality\ChemistryJudge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChemistryEntryTest extends TestCase
{
    use RefreshDatabase;

    private function heat(?string $grade = 'EN-GJS-500-7'): Heat
    {
        $f = Furnace::create(['code' => 'IF-'.uniqid(), 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED',
            'iron_grade_id' => $grade ? IronGrade::where('code', $grade)->value('id') : null]);
    }

    public function test_in_limits_analysis_is_saved_and_numbered(): void
    {
        $heat = $this->heat();
        $ok = ['sample_stage' => 'AFTER_TREATMENT', 'test_method' => 'SPECTROMETER', 'carbon_percent' => 3.65, 'silicon_percent' => 2.5, 'manganese_percent' => 0.3, 'phosphorus_percent' => 0.03, 'sulphur_percent' => 0.01, 'copper_percent' => 0.4, 'magnesium_percent' => 0.045];
        $this->post(route('heats.chemistry.store', $heat), $ok)->assertSessionHasNoErrors();
        $this->post(route('heats.chemistry.store', $heat), $ok)->assertSessionHasNoErrors();

        $rows = HeatChemistryReading::where('heat_id', $heat->id)->orderBy('reading_number')->get();
        $this->assertSame([1, 2], $rows->pluck('reading_number')->all());
        $this->assertTrue($rows[0]->is_accepted);
        $this->assertEqualsWithDelta(4.49, (float) $rows[0]->carbon_equivalent, 0.01);
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Chemical analysis')->assertSee('IN LIMITS');
    }

    public function test_out_of_limits_is_flagged_with_the_reason(): void
    {
        $heat = $this->heat();
        $res = $this->post(route('heats.chemistry.store', $heat), ['sample_stage' => 'FINAL', 'test_method' => 'SPECTROMETER', 'carbon_percent' => 3.6, 'silicon_percent' => 2.5, 'manganese_percent' => 0.9, 'magnesium_percent' => 0.02]);
        $res->assertSessionHas('success');
        $this->assertStringContainsString('OUT OF LIMITS', session('success'));
        $this->assertFalse(HeatChemistryReading::first()->is_accepted);
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('OUT OF LIMITS')->assertSee('Mn 0.9 % is above the maximum');
    }

    public function test_heat_without_a_grade_saves_but_is_not_judged_and_empty_entry_is_refused(): void
    {
        $heat = $this->heat(null);
        $this->post(route('heats.chemistry.store', $heat), ['sample_stage' => 'BASE', 'test_method' => 'SPECTROMETER', 'carbon_percent' => 3.5])->assertSessionHasNoErrors();
        $this->assertNull(HeatChemistryReading::first()->is_accepted);
        $this->post(route('heats.chemistry.store', $heat), ['sample_stage' => 'BASE', 'test_method' => 'SPECTROMETER'])->assertSessionHasErrors('chemistry');
    }

    public function test_verify_marks_the_reading(): void
    {
        $heat = $this->heat();
        $this->post(route('heats.chemistry.store', $heat), ['sample_stage' => 'BASE', 'test_method' => 'SPECTROMETER', 'carbon_percent' => 3.6, 'silicon_percent' => 2.4]);
        $r = HeatChemistryReading::firstOrFail();
        $this->post(route('heats.chemistry.verify', [$heat, $r]))->assertSessionHasNoErrors();
        $this->assertTrue($r->fresh()->is_verified);
        $other = $this->heat();
        $this->post(route('heats.chemistry.verify', [$other, $r]))->assertNotFound();
    }

    public function test_carbon_equivalent_needs_carbon_and_silicon(): void
    {
        $j = new ChemistryJudge();
        $this->assertNull($j->carbonEquivalent(['carbon_percent' => 3.6]));
        $this->assertEqualsWithDelta(4.5, $j->carbonEquivalent(['carbon_percent' => 3.6, 'silicon_percent' => 2.4, 'phosphorus_percent' => 0.3]), 0.001);
    }
}
