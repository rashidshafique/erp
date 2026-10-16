<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HeatTreatment;
use App\Models\IronGrade;
use App\Services\QualityGates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentTest extends TestCase
{
    use RefreshDatabase;

    private function heat(): Heat
    {
        $f = Furnace::create(['code' => 'IF-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => 'H-T1', 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED', 'iron_grade_id' => IronGrade::where('code', 'EN-GJS-500-7')->value('id')]);
    }

    private function check(Heat $heat, string $label): ?array
    {
        return collect(app(QualityGates::class)->pourGate($heat->fresh())['checks'])->firstWhere('label', $label);
    }

    public function test_treatment_is_recorded_with_addition_percentage(): void
    {
        $heat = $this->heat();
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('Metal treatment and inoculation');
        $this->post(route('heats.treatments.store', $heat), ['treatment_type' => 'MG_TREATMENT', 'material' => 'FeSiMg 6%', 'amount_kg' => 15, 'metal_treated_kg' => 1000, 'method' => 'SANDWICH', 'temperature_c' => 1490])->assertSessionHasNoErrors();
        $t = HeatTreatment::firstOrFail();
        $this->assertEquals(1.5, $t->addition_pct);
        $this->post(route('heats.treatments.store', $heat), ['treatment_type' => 'MG_TREATMENT', 'treated_at' => now()->addDay()->toDateTimeString()])->assertSessionHasErrors('treatment');
        $this->post(route('heats.treatments.store', $heat), ['treatment_type' => 'NONSENSE'])->assertSessionHasErrors('treatment_type');
        $this->delete(route('heats.treatments.destroy', [$heat, $t]))->assertSessionHasNoErrors();
        $this->assertSame(0, HeatTreatment::count());
    }

    public function test_gate_warns_by_default_and_blocks_when_enforced(): void
    {
        $heat = $this->heat();
        $this->assertNull($this->check($heat, 'Magnesium treatment')['ok']);       // warning only
        $this->put(route('process-controls.update'), ['enforce_treatment_records' => 1, 'max_fade_minutes' => 15])->assertSessionHasNoErrors();
        $this->assertFalse($this->check($heat, 'Magnesium treatment')['ok']);
        $this->assertFalse($this->check($heat, 'Inoculation')['ok']);

        $heat->treatments()->create(['treatment_type' => 'MG_TREATMENT', 'treated_at' => now()->subMinutes(30)]);
        $this->assertFalse($this->check($heat, 'Treatment fade')['ok']);
        $heat->treatments()->create(['treatment_type' => 'MG_TREATMENT', 'treated_at' => now()->subMinutes(5)]);
        $this->assertTrue($this->check($heat, 'Treatment fade')['ok']);
        $heat->treatments()->create(['treatment_type' => 'INOCULATION_STREAM', 'treated_at' => now()->subMinutes(2)]);
        $this->assertTrue($this->check($heat, 'Inoculation')['ok']);
        $this->assertSame(15, \App\Http\Controllers\ProcessControlController::maxFadeMinutes());
        $this->get(route('settings.edit'))->assertOk()->assertSee('Process controls');
    }

    public function test_pattern_cooling_time_is_saved(): void
    {
        $pat = \App\Models\Pattern::create(['code' => 'P-C', 'name' => 'Cooling test']);
        $rev = \App\Models\PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 1]);
        $this->get(route('patterns.show', $pat))->assertOk()->assertSee('Cooling before shakeout');
        $this->put(route('pattern-revisions.cooling', $rev->id), ['min_cooling_minutes' => 240])->assertSessionHasNoErrors();
        $this->assertSame(240, (int) \Illuminate\Support\Facades\DB::table('pattern_revisions')->where('id', $rev->id)->value('min_cooling_minutes'));
        $this->put(route('pattern-revisions.cooling', $rev->id), ['min_cooling_minutes' => ''])->assertSessionHasNoErrors();
        $this->assertNull(\Illuminate\Support\Facades\DB::table('pattern_revisions')->where('id', $rev->id)->value('min_cooling_minutes'));
    }
}
