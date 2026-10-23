<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The nominal weight of a product now follows the net weight of its current revision (the latest approved one, else the latest
 * that is not obsolete). A weight that was only on the product moves onto that revision first, so nothing is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('products')->get(['id', 'nominal_weight_kg']) as $product) {
            $revisions = DB::table('product_revisions')->where('product_id', $product->id)->orderByDesc('created_at')->get(['id', 'status', 'net_weight_kg']);
            $current = $revisions->firstWhere('status', 'APPROVED') ?? $revisions->first(fn ($r) => $r->status !== 'OBSOLETE') ?? $revisions->first();
            if (! $current) {
                continue;
            }
            if ($current->net_weight_kg === null && $product->nominal_weight_kg !== null) {
                DB::table('product_revisions')->where('id', $current->id)->update(['net_weight_kg' => $product->nominal_weight_kg]);
                $current->net_weight_kg = $product->nominal_weight_kg;
            }
            if ($current->net_weight_kg !== null) {
                DB::table('products')->where('id', $product->id)->update(['nominal_weight_kg' => $current->net_weight_kg]);
            }
        }
    }

    public function down(): void
    {
        // The weights stay where they are; there is nothing to undo.
    }
};
