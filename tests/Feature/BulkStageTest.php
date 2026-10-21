<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\ProductionStageDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class BulkStageTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    public function test_ticked_lots_pass_several_stages_together_and_rejects_come_from_the_oldest_lot(): void
    {
        $a = $this->castingLot();
        $b = $this->castingLot();
        $b->update(['created_at' => now()->addMinute()]);
        $shakeout = ProductionStageDefinition::where('code', 'SHAKEOUT')->firstOrFail();
        $blast = ProductionStageDefinition::where('code', 'SHOT_BLAST')->firstOrFail();
        $fettle = ProductionStageDefinition::where('code', 'FETTLING')->firstOrFail();
        $this->post(route('lots.bulk-stage'), ['lots' => [$a->id, $b->id], 'stages' => [$shakeout->id], 'cooling_override_reason' => 'Test'])->assertSessionHasNoErrors();

        $this->post(route('lots.bulk-stage'), ['lots' => [$a->id, $b->id], 'stages' => [$fettle->id, $blast->id], 'rejected' => 7, 'rejection_reason' => 'Cracked'])->assertSessionHas('success');

        $this->assertSame(5, $a->fresh()->quantity_rejected);
        $this->assertSame(2, $b->fresh()->quantity_rejected);
        $this->assertSame(['SHAKEOUT', 'SHOT_BLAST'], $a->stageEvents()->get()->map(fn ($e) => $e->stage->code)->all());   // nothing left to fettle
        $this->assertSame(3, $b->stageEvents()->count());
    }

    public function test_a_lot_that_cannot_take_the_stage_is_skipped_and_reported(): void
    {
        $lot = $this->castingLot();
        $lot->update(['status' => 'SCRAPPED']);
        $blast = ProductionStageDefinition::where('code', 'SHOT_BLAST')->firstOrFail();

        $this->post(route('lots.bulk-stage'), ['lots' => [$lot->id], 'stages' => [$blast->id]])->assertSessionHas('warning');

        $this->assertSame(0, $lot->stageEvents()->count());
    }

    public function test_rejected_pieces_need_a_reason(): void
    {
        $lot = $this->castingLot();
        $blast = ProductionStageDefinition::where('code', 'SHOT_BLAST')->firstOrFail();

        $this->post(route('lots.bulk-stage'), ['lots' => [$lot->id], 'stages' => [$blast->id], 'rejected' => 1])->assertSessionHasErrors('rejection_reason');
    }

    public function test_the_queue_shows_a_tick_box_for_each_open_lot(): void
    {
        $lot = $this->castingLot();

        $this->get(route('lots.queue'))->assertOk()->assertSee('Move ticked lots')->assertSee($lot->lot_number);
    }
}
