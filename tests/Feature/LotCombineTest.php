<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\CastingLotStageEvent;
use App\Models\NonconformanceReport;
use App\Models\PourMouldAssembly;
use App\Models\ProductionStageDefinition;
use App\Models\QualityInspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class LotCombineTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    /** Another lot of the same pour and order, from a mould of its own. */
    private function lotFromSamePour(CastingLot $lot, string $code): CastingLot
    {
        $mould = $lot->pourMouldAssembly->mouldAssembly->replicate(['id'])->fill(['assembly_number' => 'MA-'.uniqid()]);
        $mould->save();
        $row = PourMouldAssembly::create(['pour_id' => $lot->pourMouldAssembly->pour_id, 'mould_assembly_id' => $mould->id, 'production_order_id' => $lot->production_order_id, 'castings_poured_qty' => 5]);
        $other = $lot->replicate(['id'])->fill(['code' => $code, 'lot_number' => 'LOT-'.uniqid(), 'pour_mould_assembly_id' => $row->id]);
        $other->save();
        $other->pourMouldAssemblies()->attach($row->id, ['quantity' => 5]);

        return $other;
    }

    private function stageEvent(CastingLot $lot, ProductionStageDefinition $stage, int $rejected = 0): void
    {
        CastingLotStageEvent::create(['casting_lot_id' => $lot->id, 'stage_definition_id' => $stage->id, 'quantity_in' => 5, 'quantity_accepted' => 5 - $rejected, 'quantity_rejected' => $rejected, 'quantity_out' => 5 - $rejected, 'rejection_reason' => $rejected ? 'Shrinkage' : null]);
    }

    public function test_lots_of_one_heat_and_status_become_the_oldest_lot_with_their_counts_and_records_added_up(): void
    {
        $stage = ProductionStageDefinition::orderBy('sequence')->firstOrFail();
        $first = $this->castingLot();
        $first->update(['code' => '261020-09']);
        $first->pourMouldAssemblies()->attach($first->pour_mould_assembly_id, ['quantity' => 5]);
        $second = $this->lotFromSamePour($first, '261020-10');
        $this->stageEvent($first, $stage);
        $this->stageEvent($second, $stage, 1);
        $inspection = QualityInspection::create(['inspection_number' => 'INS-'.uniqid(), 'casting_lot_id' => $second->id, 'inspection_type' => 'FINAL', 'status' => 'COMPLETED', 'overall_disposition' => 'ACCEPT', 'quantity_inspected' => 4, 'quantity_accepted' => 4]);
        $ncr = NonconformanceReport::create(['ncr_number' => 'NCR-'.uniqid(), 'casting_lot_id' => $second->id, 'quality_inspection_id' => $inspection->id, 'status' => 'OPEN', 'quantity_affected' => 1, 'title' => 'Shrinkage', 'description' => 'Shrinkage']);

        $this->post(route('lots.bulk'), ['action' => 'combine', 'lots' => [$second->id, $first->id]])->assertSessionHas('success');

        $this->assertNull(CastingLot::find($second->id));
        $kept = $first->fresh();
        $this->assertSame([10, 9, 1], [$kept->quantity_poured, $kept->quantity_good, $kept->quantity_rejected]);
        $this->assertSame(2, $kept->pourMouldAssemblies()->count());
        $event = $kept->stageEvents()->sole();
        $this->assertSame([10, 9, 1], [$event->quantity_in, $event->quantity_out, $event->quantity_rejected]);
        $this->assertSame($kept->id, $inspection->fresh()->casting_lot_id);
        $this->assertSame($kept->id, $ncr->fresh()->casting_lot_id);
        $this->assertStringContainsString('combined 2 lots: 261020-09, 261020-10', $kept->notes);
    }

    public function test_lots_of_another_status_or_heat_or_packed_are_left_alone(): void
    {
        $lot = $this->castingLot();
        $held = $this->lotFromSamePour($lot, '261020-02');
        $held->update(['status' => 'ON_HOLD']);
        $packed = $this->lotFromSamePour($lot, '261020-03');
        $packed->update(['status' => 'PACKED']);
        $otherHeat = $this->castingLot();

        $this->post(route('lots.bulk'), ['action' => 'combine', 'lots' => [$lot->id, $held->id, $packed->id, $otherHeat->id]])->assertSessionHasErrors('combine');

        $this->assertSame(4, CastingLot::count());
    }
}
