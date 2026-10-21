<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class LotBulkStatusTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    public function test_ticked_lots_can_be_held_and_released_together(): void
    {
        $a = $this->castingLot();
        $b = $this->castingLot();

        $this->post(route('lots.bulk'), ['action' => 'hold', 'lots' => [$a->id, $b->id]])->assertSessionHasErrors('reason');
        $this->post(route('lots.bulk'), ['action' => 'hold', 'reason' => 'Sand check', 'lots' => [$a->id, $b->id]])->assertSessionHas('success');
        $this->assertSame('ON_HOLD', $a->fresh()->status);

        $this->post(route('lots.bulk'), ['action' => 'release', 'lots' => [$a->id]])->assertSessionHas('success');
        $this->assertSame('IN_PRODUCTION', $a->fresh()->status);
        $this->assertSame('ON_HOLD', $b->fresh()->status);
    }

    public function test_all_matching_the_filter_are_released_and_other_statuses_are_left_alone(): void
    {
        $held = $this->castingLot();
        $held->update(['status' => 'ON_HOLD']);
        $ready = $this->castingLot();
        $ready->update(['status' => 'READY']);

        $this->post(route('lots.bulk'), ['action' => 'release', 'all_matching' => 1, 'status' => 'ON_HOLD'])->assertSessionHas('success');

        $this->assertSame('IN_PRODUCTION', $held->fresh()->status);
        $this->assertSame('READY', $ready->fresh()->status);
    }

    public function test_only_first_iron_holds_are_released_by_the_first_iron_button(): void
    {
        $iron = $this->castingLot();
        $iron->update(['status' => 'ON_HOLD', 'notes' => '20 Oct 18:18 hold: first iron of heat H-1, held until QC passes']);
        $contained = $this->castingLot();
        $contained->update(['status' => 'ON_HOLD', 'notes' => "20 Oct 18:18 hold: first iron of heat H-1, held until QC passes\n20 Oct 19:00 hold: containment, bad chemistry"]);

        $this->get(route('lots.index'))->assertSee('1 lot(s) are on hold only because they were first iron');
        $this->post(route('lots.release-first-iron'))->assertSessionHas('success');

        $this->assertSame('IN_PRODUCTION', $iron->fresh()->status);
        $this->assertSame('ON_HOLD', $contained->fresh()->status);
    }
}
