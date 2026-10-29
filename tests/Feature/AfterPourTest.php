<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\ProductionStageDefinition;
use App\Services\Production\AfterPour;
use App\Services\Production\ProcessRoutes;
use App\Services\Quality\QuickInspection;
use App\Support\QualityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

/** Castings moved through knock-out, fettling and shot blast in batches before quality control. */
class AfterPourTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $ids = collect(['SHAKEOUT', 'CUTTING', 'SHOT_BLAST', 'FINAL_INSPECTION'])->map(fn ($c) => ProductionStageDefinition::where('code', $c)->value('id'))->all();
        app(ProcessRoutes::class)->saveDefault($ids);   // the floor's route: knock-out, fettling, shot blast, then final inspection
    }

    private function step(string $code): ProductionStageDefinition
    {
        return ProductionStageDefinition::where('code', $code)->firstOrFail();
    }

    /** @return array{0: CastingLot, 1: CastingLot} two lots of 5 pieces of one product, one mould each */
    private function twoLots(): array
    {
        $first = $this->castingLot();
        $second = $this->castingLot();
        $second->update(['product_id' => $first->product_id, 'product_revision_id' => $first->product_revision_id, 'created_at' => now()->addMinute()]);

        return [$first, $second->fresh()];
    }

    public function test_batches_move_through_the_steps_oldest_lot_first_and_a_lot_can_sit_at_two_steps(): void
    {
        [$first, $second] = $this->twoLots();
        $rev = $first->product_revision_id;
        $this->assertSame(['SHAKEOUT', 'CUTTING', 'SHOT_BLAST'], app(AfterPour::class)->stepsFor($rev)->pluck('code')->all());
        $this->get(route('after-pour.index'))->assertOk()->assertSee('Shakeout')->assertSee('10 pcs')->assertSee('≈ 2 moulds');

        $this->post(route('after-pour.store'), ['stage_definition_id' => $this->step('SHAKEOUT')->id, 'product_revision_id' => $rev])->assertSessionHasNoErrors();
        $this->post(route('after-pour.store'), ['stage_definition_id' => $this->step('CUTTING')->id, 'product_revision_id' => $rev, 'quantity' => 1, 'unit' => 'MOULDS',
            'rejected' => 1])->assertSessionHasErrors('batch');
        $this->post(route('after-pour.store'), ['stage_definition_id' => $this->step('CUTTING')->id, 'product_revision_id' => $rev, 'quantity' => 1, 'unit' => 'MOULDS',
            'rejected' => 1, 'rejection_reason' => 'Cold shut'])->assertSessionHasNoErrors();

        $this->assertSame(['waiting' => [$this->step('SHAKEOUT')->id => 0, $this->step('CUTTING')->id => 0, $this->step('SHOT_BLAST')->id => 4], 'done' => 0], app(AfterPour::class)->progress($first->fresh()));
        $this->assertSame(5, app(AfterPour::class)->progress($second->fresh())['waiting'][$this->step('CUTTING')->id], 'the mould came from the oldest lot');
        $this->assertSame(1, $first->fresh()->quantity_rejected);

        // Part of the second lot is shot blasted before the rest is fettled: it sits at two steps.
        $this->post(route('after-pour.store'), ['stage_definition_id' => $this->step('SHOT_BLAST')->id, 'product_revision_id' => $rev])->assertSessionHasNoErrors();
        $this->post(route('after-pour.store'), ['stage_definition_id' => $this->step('CUTTING')->id, 'product_revision_id' => $rev, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->post(route('after-pour.store'), ['stage_definition_id' => $this->step('SHOT_BLAST')->id, 'product_revision_id' => $rev, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->post(route('after-pour.store'), ['stage_definition_id' => $this->step('CUTTING')->id, 'product_revision_id' => $rev])->assertSessionHasNoErrors();
        $this->assertSame(['waiting' => [$this->step('SHAKEOUT')->id => 0, $this->step('CUTTING')->id => 0, $this->step('SHOT_BLAST')->id => 3], 'done' => 2], app(AfterPour::class)->progress($second->fresh()));

        $this->post(route('after-pour.store'), ['stage_definition_id' => $this->step('SHOT_BLAST')->id, 'product_revision_id' => $rev, 'quantity' => 9])->assertSessionHasErrors('batch');
    }

    public function test_quick_qc_waits_for_the_steps_when_the_rule_says_so(): void
    {
        [$first] = $this->twoLots();
        $qc = app(QuickInspection::class);
        $this->assertSame(5, $qc->toCheck($first), 'by default quality control takes castings straight after the pour');

        QualityRules::set('qc_after_steps', 'STEPS');
        $this->assertSame(0, $qc->toCheck($first->fresh()));
        foreach (['SHAKEOUT', 'CUTTING', 'SHOT_BLAST'] as $code) {
            $this->post(route('after-pour.store'), ['stage_definition_id' => $this->step($code)->id, 'product_revision_id' => $first->product_revision_id, 'quantity' => 3]);
        }
        $this->assertSame(3, $qc->toCheck($first->fresh()));
        $this->get(route('after-pour.index'))->assertSee('Shot blasting');
    }
}
