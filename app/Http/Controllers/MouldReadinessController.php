<?php

namespace App\Http\Controllers;

use App\Models\Furnace;
use App\Models\MouldAssembly;
use App\Models\MouldingRun;
use App\Services\Planning\HeatPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Moulds that are made and waiting for metal, grouped by the iron grade they need. */
class MouldReadinessController extends Controller
{
    /** Sheera and green sand moulds lose moisture and strength standing; flag them past this age. */
    public const STALE_HOURS = 24;

    public function index(HeatPlanner $planner): View
    {
        $groups = [];
        $add = function (?string $key, string $label, array $row) use (&$groups) {
            $g = &$groups[$key ?? 'none'];
            $g ??= ['label' => $label, 'unassembled' => 0, 'ready' => 0, 'castings' => 0, 'metal_kg' => 0.0, 'oldest' => null, 'orders' => []];
            foreach (['unassembled', 'ready', 'castings'] as $f) {
                $g[$f] += $row[$f] ?? 0;
            }
            $g['metal_kg'] += $row['metal_kg'] ?? 0;
            if (! empty($row['oldest']) && (! $g['oldest'] || $row['oldest'] < $g['oldest'])) {
                $g['oldest'] = $row['oldest'];
            }
            if (! empty($row['order'])) {
                $g['orders'][$row['order']] = true;
            }
        };

        // Assembled, not yet on a pour
        $asm = MouldAssembly::with('mouldingRun.productionOrder.productRevision')->where('status', 'PREPARED')->whereDoesntHave('pourAssignment')->get();
        foreach ($asm as $a) {
            $order = $a->mouldingRun?->productionOrder;
            $grade = $order?->productRevision ? $planner->gradeFor($order->productRevision) : null;
            $q = DB::table('mould_cavities')->join('mould_layers', 'mould_layers.id', '=', 'mould_cavities.mould_layer_id')
                ->join('pattern_revision_cavities as prc', fn ($j) => $j->on('prc.pattern_revision_id', '=', 'mould_cavities.pattern_revision_id')->on('prc.cavity_number', '=', 'mould_cavities.cavity_number'))
                ->where('mould_layers.mould_assembly_id', $a->id)->where('mould_cavities.is_active', true);
            $add($grade?->id, $grade?->code ?? 'No grade resolved', [
                'ready' => 1, 'castings' => (int) (clone $q)->sum('mould_cavities.castings_per_cavity'), 'metal_kg' => (float) (clone $q)->sum('prc.poured_weight_kg'),
                'oldest' => $a->assembled_at ?? $a->created_at, 'order' => $order?->production_order_number,
            ]);
        }

        // Accepted moulds not yet assembled (counted but not yet in the cavity records)
        $runs = MouldingRun::with('productionOrder.productRevision')->where('status', '!=', 'CANCELLED')->withCount('mouldAssemblies')->get();
        foreach ($runs as $r) {
            $left = (int) $r->accepted_mould_qty - $r->mould_assemblies_count;
            if ($left > 0 && $r->productionOrder) {
                $grade = $r->productionOrder->productRevision ? $planner->gradeFor($r->productionOrder->productRevision) : null;
                $add($grade?->id, $grade?->code ?? 'No grade resolved', ['unassembled' => $left, 'oldest' => $r->completed_at ?? $r->started_at, 'order' => $r->productionOrder->production_order_number]);
            }
        }

        $furnaces = Furnace::where('status', 'ACTIVE')->whereNotNull('rated_capacity_kg')->orderBy('code')->get();

        return view('mould-readiness.index', ['groups' => collect($groups)->sortBy('label'), 'furnaces' => $furnaces, 'staleHours' => self::STALE_HOURS]);
    }
}
