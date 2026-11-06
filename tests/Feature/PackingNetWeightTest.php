<?php

namespace Tests\Feature;

use App\Domains\Dispatch\Services\ShipmentService;
use App\Models\CastingLot;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\MouldAssembly;
use App\Models\MouldConfiguration;
use App\Models\MouldingRun;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use App\Models\Product;
use App\Models\QualityInspection;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackingNetWeightTest extends TestCase
{
    use RefreshDatabase;

    private function lot(?float $lotNet): CastingLot
    {
        $product = Product::create(['code' => 'RING-'.uniqid(), 'name' => 'Ring']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED', 'net_weight_kg' => 2.5]);

        $order = ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $product->id, 'product_revision_id' => $revision->id, 'order_type' => 'STOCK', 'status' => 'IN_PROGRESS', 'planned_qty' => 10, 'uom' => 'piece']);
        $patternRevision = PatternRevision::create(['pattern_id' => Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE'])->id, 'status' => 'APPROVED']);
        $run = MouldingRun::create(['run_number' => 'MR-'.uniqid(), 'production_order_id' => $order->id, 'pattern_revision_id' => $patternRevision->id, 'run_date' => today(), 'status' => 'COMPLETED', 'moulds_made_qty' => 1, 'accepted_mould_qty' => 1]);
        $configuration = MouldConfiguration::create(['code' => 'MC-'.uniqid(), 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        $assembly = MouldAssembly::create(['assembly_number' => 'MA-'.uniqid(), 'mould_configuration_id' => $configuration->id, 'moulding_run_id' => $run->id, 'status' => 'POURED']);
        $furnace = Furnace::create(['code' => 'CU-'.uniqid(), 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'rated_capacity_kg' => 5000, 'nominal_melting_rate_kg_per_hour' => 3000]);
        $heat = Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'CLOSED']);
        $pour = Pour::create(['heat_id' => $heat->id, 'pour_number' => 'P-'.uniqid(), 'status' => 'COMPLETED', 'started_at' => now(), 'completed_at' => now()]);
        $row = PourMouldAssembly::create(['pour_id' => $pour->id, 'mould_assembly_id' => $assembly->id, 'production_order_id' => $order->id, 'castings_poured_qty' => 4]);

        $lot = CastingLot::create(['lot_number' => 'LOT-'.uniqid(), 'pour_mould_assembly_id' => $row->id, 'production_order_id' => $order->id, 'product_id' => $product->id, 'product_revision_id' => $revision->id, 'status' => 'READY',
            'quantity_poured' => 4, 'quantity_good' => 4, 'quantity_rejected' => 0, 'net_casting_weight_kg' => $lotNet]);
        QualityInspection::create(['inspection_number' => 'INS-'.uniqid(), 'casting_lot_id' => $lot->id, 'inspection_type' => 'FINAL', 'status' => 'COMPLETED', 'overall_disposition' => 'ACCEPT', 'quantity_inspected' => 4, 'quantity_accepted' => 4]);

        return $lot;
    }

    public function test_a_lot_with_no_weight_of_its_own_is_packed_at_the_products_casting_weight(): void
    {
        $lot = $this->lot(null);

        $pack = app(ShipmentService::class)->pack([$lot->id => 4], ['packed_by' => 'Test']);

        $this->assertEquals(10.0, (float) $pack->total_net_weight_kg);
    }

    public function test_a_lots_own_weight_still_wins(): void
    {
        $lot = $this->lot(8.0);

        $pack = app(ShipmentService::class)->pack([$lot->id => 2], ['packed_by' => 'Test']);

        $this->assertEquals(4.0, (float) $pack->total_net_weight_kg);
    }
}
