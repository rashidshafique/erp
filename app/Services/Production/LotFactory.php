<?php

namespace App\Services\Production;

use App\Models\CastingLot;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use App\Models\ProductionOrder;
use App\Support\PouringRules;
use App\Support\RelatedFiles;
use Illuminate\Support\Facades\DB;

/** Creates the casting lots of a completed pour: one lot per product revision in each mould assembly. */
class LotFactory
{
    /** @return array{lots: int, warnings: array<int, string>} */
    public function createForPour(Pour $pour): array
    {
        $warnings = [];
        $made = 0;

        DB::transaction(function () use ($pour, &$warnings, &$made) {
            $rows = PourMouldAssembly::with('mouldAssembly.mouldingRun.productionOrder', 'castingLots')->where('pour_id', $pour->id)->get();

            // Per-assembly net weight, to share the poured weight out by what each mould holds.
            $net = [];
            $groupsByRow = [];
            foreach ($rows as $row) {
                $groups = DB::table('mould_cavities')->join('mould_layers', 'mould_layers.id', '=', 'mould_cavities.mould_layer_id')
                    ->where('mould_layers.mould_assembly_id', $row->mould_assembly_id)->where('mould_cavities.is_active', true)
                    ->groupBy('mould_cavities.product_revision_id')
                    ->selectRaw('mould_cavities.product_revision_id as rev, SUM(mould_cavities.castings_per_cavity) as qty, SUM(COALESCE(mould_cavities.nominal_casting_weight_kg,0) * mould_cavities.castings_per_cavity) as kg')
                    ->get();
                $groupsByRow[$row->id] = $groups;
                $net[$row->id] = (float) $groups->sum('kg');
            }
            $totalNet = array_sum($net);
            $seq = (int) CastingLot::whereIn('pour_mould_assembly_id', $rows->pluck('id'))->count();

            foreach ($rows as $row) {
                if ($row->castingLots->isNotEmpty()) {
                    continue;   // already created
                }
                $asm = $row->mouldAssembly;
                $runOrder = $asm?->mouldingRun?->productionOrder;
                $asmWeight = $row->poured_weight_kg !== null ? (float) $row->poured_weight_kg
                    : ($pour->total_poured_weight_kg !== null && $totalNet > 0 ? round((float) $pour->total_poured_weight_kg * $net[$row->id] / $totalNet, 3) : null);
                if ($row->poured_weight_kg === null && $asmWeight !== null) {
                    $row->update(['poured_weight_kg' => $asmWeight]);
                }

                foreach ($groupsByRow[$row->id] as $g) {
                    $order = ($runOrder && $runOrder->product_revision_id === $g->rev) ? $runOrder
                        : ProductionOrder::where('product_revision_id', $g->rev)->whereIn('status', ['RELEASED', 'IN_PROGRESS'])->orderBy('required_date')->first();
                    if (! $order) {
                        $warnings[] = "{$asm->assembly_number}: {$g->qty} casting(s) of a product with no open production order were not given a lot.";

                        continue;
                    }
                    $seq++;
                    $lot = CastingLot::create([
                        'lot_number' => 'LOT-'.$pour->pour_number.'-'.str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
                        'pour_mould_assembly_id' => $row->id,
                        'production_order_id' => $order->id,
                        'product_id' => $order->product_id,
                        'product_revision_id' => $order->product_revision_id,
                        'status' => 'IN_PRODUCTION',
                        'quantity_poured' => (int) $g->qty,
                        'quantity_good' => (int) $g->qty,
                        'quantity_rejected' => 0,
                        'gross_weight_kg' => $asmWeight !== null && $net[$row->id] > 0 ? round($asmWeight * (float) $g->kg / $net[$row->id], 3) : null,
                        'net_casting_weight_kg' => $g->kg > 0 ? round((float) $g->kg, 3) : null,
                        'created_from_pour_at' => $pour->completed_at ?? now(),
                    ]);
                    $made++;
                    if ($pour->is_first_iron && PouringRules::get('hold_first_iron') === 'HOLD') {
                        $lot->update(['status' => 'ON_HOLD', 'notes' => trim($lot->notes."\n".now()->format('d M H:i').' hold: first iron of heat '.$pour->heat?->heat_number.', held until QC passes')]);
                    }
                    RelatedFiles::pin('casting_lot', $lot->id);   // keeps the drawing versions the lot was made to, when versioning is on
                    if ($order->status === 'RELEASED') {
                        $order->update(['status' => 'IN_PROGRESS', 'actual_started_at' => $order->actual_started_at ?? now()]);
                    }
                }
                $asm?->update(['status' => 'POURED']);
            }
        });

        return ['lots' => $made, 'warnings' => $warnings];
    }
}
