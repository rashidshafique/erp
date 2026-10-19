<?php

namespace Tests\Concerns;

use App\Models\MouldingStation;
use App\Models\MouldingTeam;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;

/** A released stock order with a two-cavity matchplate, a team and a station, and the form fields for putting them on a work list. */
trait BuildsWorkLists
{
    private function world(): array
    {
        $product = Product::create(['code' => 'FLG-1', 'name' => 'Flange']);
        $rev = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $pat = Pattern::create(['code' => 'MP-7', 'name' => 'Flange plate', 'pattern_type' => 'MATCHPLATE']);
        $pr = PatternRevision::create(['pattern_id' => $pat->id, 'revision' => 'A', 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $pr->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $rev->id, 'casting_weight_kg' => 5, 'poured_weight_kg' => 8]);
        $pr->cavityMap()->create(['cavity_number' => 2, 'product_revision_id' => $rev->id, 'casting_weight_kg' => 5, 'poured_weight_kg' => 8]);
        $order = ProductionOrder::create(['production_order_number' => 'PO-S1', 'product_id' => $product->id, 'product_revision_id' => $rev->id, 'order_type' => 'STOCK',
            'status' => 'RELEASED', 'planned_qty' => 100, 'uom' => 'piece']);
        $team = MouldingTeam::create(['code' => 'T1', 'name' => 'Team Alpha', 'lead_name' => 'Rashid', 'member_count' => 5, 'status' => 'ACTIVE']);
        $st = MouldingStation::create(['code' => 'MP-1', 'name' => 'Match plate machine 1', 'station_type' => 'MATCHPLATE_MACHINE', 'status' => 'ACTIVE']);

        return compact('product', 'rev', 'pr', 'order', 'team', 'st');
    }

    private function item(array $w, array $over = []): array
    {
        return $over + ['moulding_team_id' => $w['team']->id, 'moulding_station_id' => $w['st']->id, 'production_order_id' => $w['order']->id,
            'pattern_revision_id' => $w['pr']->id, 'sand_system' => 'Sheera', 'planned_moulds' => 30];
    }
}
