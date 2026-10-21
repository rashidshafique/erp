<?php

namespace App\Services\Production;

use App\Models\CastingLot;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use App\Models\ProductionOrder;
use App\Support\OrderRules;
use App\Support\PouringRules;
use App\Support\QualityRules;
use App\Support\RelatedFiles;
use Illuminate\Support\Facades\DB;

/** Creates the casting lots of a completed pour: one per product revision in each mould assembly, or one per product for the whole pour when lots are grouped. */
class LotFactory
{
    /** @return array{lots: int, warnings: array<int, string>} */
    public function createForPour(Pour $pour): array
    {
        $warnings = [];
        $made = 0;
        $touchedOrders = [];

        DB::transaction(function () use ($pour, &$warnings, &$made, &$touchedOrders) {
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
            $seq = (int) CastingLot::where('lot_number', 'like', 'LOT-'.$pour->pour_number.'-%')->count();
            $alreadyMade = DB::table('casting_lot_pour_mould_assembly')->whereIn('pour_mould_assembly_id', $rows->pluck('id'))->pluck('pour_mould_assembly_id')->all();

            // One entry per product revision in each mould, with its order and its share of the weight.
            $entries = [];
            foreach ($rows as $row) {
                if ($row->castingLots->isNotEmpty() || in_array($row->id, $alreadyMade, true)) {
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
                    $entries[] = [
                        'row' => $row, 'order' => $order, 'qty' => (int) $g->qty, 'net' => (float) $g->kg,
                        'gross' => $asmWeight !== null && $net[$row->id] > 0 ? round($asmWeight * (float) $g->kg / $net[$row->id], 3) : null,
                    ];
                }
                $asm?->update(['status' => 'POURED']);
            }

            // Grouped by pour: all the moulds' castings of one product and order make one lot. Otherwise each mould's castings are a lot.
            $lots = collect($entries)->groupBy(fn ($e) => QualityRules::groupsByPour() ? $e['order']->id : $e['row']->id.'|'.$e['order']->id);
            foreach ($lots as $group) {
                $order = $group[0]['order'];
                $seq++;
                $net = (float) $group->sum('net');
                $gross = $group->contains(fn ($e) => $e['gross'] !== null) ? round((float) $group->sum('gross'), 3) : null;
                $lot = CastingLot::create([
                    'lot_number' => 'LOT-'.$pour->pour_number.'-'.str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
                    'pour_mould_assembly_id' => $group[0]['row']->id,
                    'production_order_id' => $order->id,
                    'product_id' => $order->product_id,
                    'product_revision_id' => $order->product_revision_id,
                    'status' => 'IN_PRODUCTION',
                    'quantity_poured' => (int) $group->sum('qty'),
                    'quantity_good' => (int) $group->sum('qty'),
                    'quantity_rejected' => 0,
                    'gross_weight_kg' => $gross,
                    'net_casting_weight_kg' => $net > 0 ? round($net, 3) : null,
                    'created_from_pour_at' => $pour->completed_at ?? now(),
                ]);
                foreach ($group->groupBy(fn ($e) => $e['row']->id) as $rowId => $parts) {
                    $lot->pourMouldAssemblies()->attach($rowId, ['quantity' => (int) $parts->sum('qty')]);
                }
                $made++;
                if ($pour->is_first_iron && PouringRules::get('hold_first_iron') === 'HOLD') {
                    $lot->update(['status' => 'ON_HOLD', 'notes' => trim($lot->notes."\n".now()->displayShortDateTime().' hold: first iron of heat '.$pour->heat?->heat_number.', held until QC passes')]);
                }
                RelatedFiles::pin('casting_lot', $lot->id);   // keeps the drawing versions the lot was made to, when versioning is on
                $order->markInProgress();
                $touchedOrders[$order->id] = $order;
            }

            if (OrderRules::completesAtPour()) {
                foreach ($touchedOrders as $order) {
                    $this->completeOrderWhenPoured($order->fresh());
                }
            }
        });

        return ['lots' => $made, 'warnings' => $warnings];
    }

    /** Under the "complete at pour" order rule, an order is complete once the castings poured for it reach the planned quantity. */
    private function completeOrderWhenPoured(ProductionOrder $order): void
    {
        if ($order->uom !== 'piece' || ! in_array($order->status, ['RELEASED', 'IN_PROGRESS'], true)) {
            return;
        }
        if ((float) CastingLot::where('production_order_id', $order->id)->sum('quantity_poured') >= (float) $order->planned_qty) {
            $order->update(['status' => 'COMPLETED', 'actual_completed_at' => now()]);
        }
    }
}
