<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\IronGrade;
use App\Services\QualityGates;
use App\Support\PouringRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class MaterialCheckWaiverTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    /** A lot whose heat has a grade with a chemistry window and no chemistry reading, with a completed final inspection and no NCR. */
    private function heldLot(): CastingLot
    {
        $lot = $this->castingLot();
        $lot->pourMouldAssembly->pour->heat->update(['iron_grade_id' => IronGrade::where('code', 'EN-GJL-250')->value('id')]);
        DB::table('quality_inspections')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'inspection_number' => 'QI-'.uniqid(), 'casting_lot_id' => $lot->id, 'inspection_type' => 'FINAL', 'status' => 'COMPLETED',
            'quantity_accepted' => 5, 'quantity_rejected' => 0, 'quantity_rework' => 0, 'quantity_pending' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $lot->fresh();
    }

    private function readiness(CastingLot $lot): array
    {
        return app(QualityGates::class)->lotReadiness($lot->fresh());
    }

    public function test_packing_follows_the_pour_mode_by_default_so_a_cupola_heat_is_not_blocked(): void
    {
        $lot = $this->heldLot();

        $r = $this->readiness($lot);
        $this->assertTrue($r['ok']);
        $this->assertFalse($r['waivable']);
        $this->assertStringContainsString('Advisory', collect($r['checks'])->firstWhere('label', 'Chemistry (heat '.$lot->pourMouldAssembly->pour->heat->heat_number.')')['detail']);
    }

    public function test_enforced_blocks_the_lot_until_someone_waives_it_with_a_reason_for_that_lot_only(): void
    {
        PouringRules::set('gate_packing', 'ENFORCED');
        $lot = $this->heldLot();
        $other = $this->heldLot();
        $this->assertFalse($this->readiness($lot)['ok']);
        $this->assertTrue($this->readiness($lot)['waivable']);
        $this->get(route('lots.show', $lot))->assertOk()->assertSee('Waive for this lot');

        $this->post(route('packing.waive-material-checks', $lot), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('packing.waive-material-checks', $lot), ['reason' => 'Customer accepts the foundry test report'])->assertSessionHasNoErrors();

        $r = $this->readiness($lot);
        $this->assertTrue($r['ok']);
        $this->assertSame('Customer accepts the foundry test report', $lot->fresh()->material_waiver_reason);
        $this->assertNotNull($lot->fresh()->material_waived_at);
        $this->assertStringContainsString('Waived for this lot', implode(' ', array_column($r['checks'], 'detail')));
        $this->assertFalse($this->readiness($other)['ok'], 'the other lot of the same kind stays blocked');
    }

    public function test_off_skips_the_two_checks_and_nothing_can_be_waived_when_nothing_holds_the_lot(): void
    {
        PouringRules::set('gate_packing', 'OFF');
        $lot = $this->heldLot();

        $r = $this->readiness($lot);
        $this->assertTrue($r['ok']);
        $this->assertSame([], array_filter($r['checks'], fn ($c) => str_starts_with($c['label'], 'Chemistry')));
        $this->post(route('packing.waive-material-checks', $lot), ['reason' => 'Not needed at all'])->assertSessionHasErrors('waiver');
        $this->assertNull($lot->fresh()->material_waived_at);
    }

    public function test_final_inspection_still_blocks_whatever_the_mode(): void
    {
        PouringRules::set('gate_packing', 'OFF');
        $lot = $this->heldLot();
        DB::table('quality_inspections')->where('casting_lot_id', $lot->id)->update(['quantity_pending' => 2]);

        $this->assertFalse($this->readiness($lot)['ok']);
    }
}
