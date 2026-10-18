<?php

namespace Tests\Concerns;

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
use App\Models\ProductionOrder;
use App\Models\ProductRevision;

/** A casting lot with the whole chain behind it (heat, pour, mould, run, order), for tests that need a lot to hang a record on. */
trait BuildsCastingLots
{
    private function castingLot(): CastingLot
    {
        $product = ProductRevision::create(['product_id' => Product::create(['code' => 'P-'.uniqid(), 'name' => 'Part'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $order = ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $product->product_id, 'product_revision_id' => $product->id, 'order_type' => 'STOCK', 'status' => 'IN_PROGRESS', 'planned_qty' => 5, 'uom' => 'piece']);
        $furnace = Furnace::create(['code' => 'CU-'.uniqid(), 'name' => 'Cupola', 'furnace_type' => 'CUPOLA', 'status' => 'ACTIVE', 'rated_capacity_kg' => 5000, 'nominal_melting_rate_kg_per_hour' => 3000]);
        $heat = Heat::create(['heat_number' => 'H-'.uniqid(), 'furnace_id' => $furnace->id, 'process_type' => 'CUPOLA', 'status' => 'MELTING']);
        $pour = Pour::create(['pour_number' => 'P-'.uniqid(), 'heat_id' => $heat->id, 'status' => 'COMPLETED']);
        $pattern = Pattern::create(['code' => 'SP-'.uniqid(), 'name' => 'Plate', 'pattern_type' => 'MATCHPLATE']);
        $patternRevision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED']);
        $run = MouldingRun::create(['run_number' => 'MR-'.uniqid(), 'production_order_id' => $order->id, 'pattern_revision_id' => $patternRevision->id, 'run_date' => today(), 'status' => 'COMPLETED', 'moulds_made_qty' => 1, 'accepted_mould_qty' => 1]);
        $configuration = MouldConfiguration::create(['code' => 'MC-'.uniqid(), 'name' => 'Cfg', 'mould_type' => 'COPE_DRAG', 'nominal_layer_count' => 2]);
        $assembly = MouldAssembly::create(['assembly_number' => 'MA-'.uniqid(), 'mould_configuration_id' => $configuration->id, 'moulding_run_id' => $run->id, 'status' => 'POURED']);
        $row = PourMouldAssembly::create(['pour_id' => $pour->id, 'mould_assembly_id' => $assembly->id, 'production_order_id' => $order->id, 'castings_poured_qty' => 5]);

        return CastingLot::create(['lot_number' => 'LOT-'.uniqid(), 'pour_mould_assembly_id' => $row->id, 'production_order_id' => $order->id, 'product_id' => $product->product_id, 'product_revision_id' => $product->id, 'status' => 'IN_PRODUCTION', 'quantity_poured' => 5, 'quantity_good' => 5, 'quantity_rejected' => 0]);
    }
}
