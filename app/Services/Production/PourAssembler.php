<?php

namespace App\Services\Production;

use App\Models\MouldAssembly;
use App\Models\Pour;
use App\Models\PourMouldAssembly;
use App\Services\Planning\HeatPlanner;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Puts mould assemblies on a pour and checks they belong there. */
class PourAssembler
{
    public function __construct(private HeatPlanner $planner)
    {
    }

    /** Castings a mould makes: active cavities across all layers, times castings per cavity. */
    public function castingsIn(MouldAssembly $asm): int
    {
        return (int) DB::table('mould_cavities')->join('mould_layers', 'mould_layers.id', '=', 'mould_cavities.mould_layer_id')
            ->where('mould_layers.mould_assembly_id', $asm->id)->where('mould_cavities.is_active', true)->sum('mould_cavities.castings_per_cavity');
    }

    /** @param  array<int, string>  $assemblyIds  $late also allows a completed pour, for moulds typed in after it was recorded */
    public function attach(Pour $pour, array $assemblyIds, bool $late = false): int
    {
        if (! in_array($pour->status, $late ? ['PLANNED', 'IN_PROGRESS', 'COMPLETED'] : ['PLANNED', 'IN_PROGRESS'], true)) {
            throw new InvalidArgumentException('Moulds can only be added to a planned pour or one in progress.');
        }
        $pour->loadMissing('heat.ironGrade');
        $added = 0;

        DB::transaction(function () use ($pour, $assemblyIds, &$added) {
            foreach (MouldAssembly::with('mouldingRun.productionOrder.productRevision', 'mouldingRun.productionOrder.salesOrderLine.salesOrder')->whereIn('id', $assemblyIds)->lockForUpdate()->get() as $asm) {
                $label = $asm->assembly_number;
                if (in_array($asm->status, ['POURED', 'SCRAPPED', 'CANCELLED'], true) || PourMouldAssembly::where('mould_assembly_id', $asm->id)->exists()) {
                    throw new InvalidArgumentException("{$label} is already poured or assigned to a pour.");
                }
                $order = $asm->mouldingRun?->productionOrder;
                if (! $order) {
                    throw new InvalidArgumentException("{$label} is not linked to a production order through its moulding run.");
                }
                if (! in_array($order->status, ['RELEASED', 'IN_PROGRESS'], true)) {
                    throw new InvalidArgumentException("{$label}: order {$order->production_order_number} is {$order->status}; release it before pouring.");
                }
                $needed = $order->productRevision ? $this->planner->gradeFor($order->productRevision) : null;
                $have = $pour->heat?->ironGrade;
                if ($needed && $have && $needed->id !== $have->id) {
                    throw new InvalidArgumentException("{$label}: order {$order->production_order_number} needs {$needed->code} but heat {$pour->heat->heat_number} is {$have->code}. Pouring it would be a material mix-up.");
                }
                $qty = $this->castingsIn($asm);
                if ($qty < 1) {
                    throw new InvalidArgumentException("{$label} has no active cavities.");
                }
                PourMouldAssembly::create(['pour_id' => $pour->id, 'mould_assembly_id' => $asm->id, 'production_order_id' => $order->id, 'castings_poured_qty' => $qty]);
                $added++;
            }
        });

        return $added;
    }

    public function detach(PourMouldAssembly $row): void
    {
        if ($row->pour?->status === 'COMPLETED' || $row->castingLots()->exists()) {
            throw new InvalidArgumentException('This pour is completed and lots exist; the mould cannot be removed.');
        }
        $row->delete();
    }
}
