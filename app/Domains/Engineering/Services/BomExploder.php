<?php

namespace App\Domains\Engineering\Services;

use App\Domains\Engineering\Models\BomLine;
use App\Models\PatternRevisionCavity;
use App\Models\ProductionOrder;
use App\Services\PatternCavityMap;
use App\Services\Planning\HeatPlanner;
use App\Services\Stock\StockLevels;
use Illuminate\Support\Collection;

/** Turns a product revision's bill of materials into what a production order needs, and sets it against stock on hand. */
class BomExploder
{
    public function __construct(private PatternCavityMap $cavities, private HeatPlanner $planner, private StockLevels $stock) {}

    /**
     * @return Collection<int, array{line: BomLine, required: ?float, on_hand: float, short: ?float, note: ?string}>
     */
    public function forOrder(ProductionOrder $order): Collection
    {
        $lines = BomLine::with('material')->where('product_revision_id', $order->product_revision_id)->get()->sortBy(fn ($l) => $l->material?->name)->values();
        if ($lines->isEmpty()) {
            return collect();
        }
        $planned = (float) $order->planned_qty;
        $moulds = $this->mouldsFor($order, $planned);
        $metalKg = $this->metalKgFor($order, $planned);
        $onHand = $this->stock->onHand($lines->pluck('material_id')->all());

        return $lines->map(function (BomLine $line) use ($planned, $moulds, $metalKg, $onHand) {
            [$base, $note] = match ($line->basis) {
                'PER_MOULD' => [$moulds, $moulds === null ? 'Needs a pattern with a cavity map to count the moulds.' : null],
                'PER_KG_POURED' => [$metalKg, $metalKg === null ? 'Needs a cavity map or a net weight to work out the metal.' : null],
                default => [$planned, null],
            };
            $required = $base === null ? null : round($base * (float) $line->quantity, 3);
            $have = (float) ($onHand[$line->material_id] ?? 0);

            return ['line' => $line, 'required' => $required, 'on_hand' => $have, 'short' => $required === null ? null : max(0.0, round($required - $have, 3)), 'note' => $note];
        });
    }

    /**
     * The liquid metal an order pours: worked out by the planner, never listed on the bill, and not stock.
     *
     * @return array{grade: ?string, kg: ?float, note: string}|null
     */
    public function metalFor(ProductionOrder $order): ?array
    {
        $revision = $order->productRevision;
        if (! $revision) {
            return null;
        }
        $perProduct = $this->planner->metalPerProduct($revision);
        $grade = $this->planner->gradeFor($revision, $order->customer_id ?? null)?->code;

        return [
            'grade' => $grade,
            'kg' => $perProduct ? round($perProduct['kg'] * (float) $order->planned_qty, 3) : null,
            'note' => $perProduct ? 'From '.$perProduct['source'].'.' : 'Needs a cavity map or a net weight to work out the metal.',
        ];
    }

    /** Moulds the planned quantity needs, from the first approved pattern revision that makes the product. */
    private function mouldsFor(ProductionOrder $order, float $planned): ?float
    {
        $patternRevision = PatternRevisionCavity::with('patternRevision')
            ->where('is_closed', false)
            ->whereHas('productRevision', fn ($q) => $q->where('product_id', $order->product_id))
            ->whereHas('patternRevision', fn ($q) => $q->where('status', 'APPROVED'))
            ->get()->pluck('patternRevision')->filter()->unique('id')
            ->first(fn ($pr) => $this->cavities->expectedProductsPerMould($pr, $order->product_id) > 0);

        return $patternRevision ? ceil($planned / $this->cavities->expectedProductsPerMould($patternRevision, $order->product_id)) : null;
    }

    private function metalKgFor(ProductionOrder $order, float $planned): ?float
    {
        $perProduct = $order->productRevision ? $this->planner->metalPerProduct($order->productRevision) : null;

        return $perProduct ? round($perProduct['kg'] * $planned, 3) : null;
    }
}
