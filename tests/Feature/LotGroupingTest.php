<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\MouldAssembly;
use App\Models\MouldCavity;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use App\Services\Production\LotFactory;
use App\Support\QualityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCastingLots;
use Tests\TestCase;

class LotGroupingTest extends TestCase
{
    use BuildsCastingLots, RefreshDatabase;

    /** A completed pour of two moulds, each holding two castings of the same product and order. */
    private function pourOfTwoMoulds(): Pour
    {
        $lot = $this->castingLot();
        $first = $lot->pourMouldAssembly;
        $pour = $first->pour;
        $pour->update(['completed_at' => now(), 'total_poured_weight_kg' => 12]);
        $second = PourMouldAssembly::create([
            'pour_id' => $pour->id, 'mould_assembly_id' => MouldAssembly::create(['assembly_number' => 'MA-'.uniqid(), 'mould_configuration_id' => $first->mouldAssembly->mould_configuration_id, 'moulding_run_id' => $first->mouldAssembly->moulding_run_id, 'status' => 'READY'])->id,
            'production_order_id' => $lot->production_order_id, 'castings_poured_qty' => 2,
        ]);
        foreach ([$first, $second] as $row) {
            $layer = $row->mouldAssembly->layers()->create(['layer_number' => 1, 'layer_type' => 'COPE', 'status' => 'READY']);
            MouldCavity::create(['mould_layer_id' => $layer->id, 'cavity_number' => 1, 'pattern_revision_id' => $row->mouldAssembly->mouldingRun->pattern_revision_id, 'product_revision_id' => $lot->product_revision_id, 'castings_per_cavity' => 2, 'nominal_casting_weight_kg' => 1.5, 'is_active' => true]);
        }
        $lot->delete();

        return $pour->fresh();
    }

    public function test_each_mould_makes_its_own_lot_by_default(): void
    {
        $pour = $this->pourOfTwoMoulds();

        $result = app(LotFactory::class)->createForPour($pour);

        $this->assertSame(2, $result['lots']);
        $this->assertSame([2, 2], CastingLot::orderBy('lot_number')->pluck('quantity_poured')->all());
    }

    public function test_grouping_by_pour_makes_one_lot_that_keeps_every_mould_linked(): void
    {
        QualityRules::set('lot_grouping', 'POUR');
        $pour = $this->pourOfTwoMoulds();

        $result = app(LotFactory::class)->createForPour($pour);

        $lot = CastingLot::firstOrFail();
        $this->assertSame(1, $result['lots']);
        $this->assertSame(4, $lot->quantity_poured);
        $this->assertSame(2, $lot->pourMouldAssemblies()->count());
        $this->assertSame(2, $lot->mouldAssemblies()->count());
        $this->assertEquals(6.0, (float) $lot->net_casting_weight_kg);
        $this->assertSame($pour->id, $lot->pourMouldAssembly->pour_id);

        app(LotFactory::class)->createForPour($pour->fresh());   // completing again creates nothing new
        $this->assertSame(1, CastingLot::count());
    }
}
