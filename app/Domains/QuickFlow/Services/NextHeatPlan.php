<?php

namespace App\Domains\QuickFlow\Services;

use App\Domains\QuickFlow\Models\QuickFloorEntry;
use App\Models\Furnace;
use App\Models\IronGrade;
use App\Models\MouldingRun;
use App\Models\PourMouldAssembly;
use App\Models\ProductionOrder;
use App\Services\FurnaceEligibility;
use App\Services\PatternCavityMap;
use App\Services\Planning\HeatPlanner;
use Illuminate\Support\Collection;

/**
 * What the foundry owes its customers and what that means for the next melt.
 *
 * For each open order: pieces ordered, moulded and poured. Metal for moulds already made comes first, then metal for
 * moulds still to make, in due-date order. A heat never holds more than the furnace's working capacity (rated size
 * times the fill factor), and its melting time comes from the furnace's nominal melting rate.
 */
class NextHeatPlan
{
    public function __construct(private HeatPlanner $planner, private FurnaceEligibility $furnaces, private PatternCavityMap $cavities) {}

    /**
     * @return array{orders: Collection<int, array<string, mixed>>, heats: list<array<string, mixed>>, warnings: list<string>}
     */
    public function build(): array
    {
        $warnings = [];
        $orders = $this->orders($warnings);
        $heats = $this->heats($orders, $warnings);

        return ['orders' => $orders, 'heats' => $heats, 'warnings' => $warnings];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function orders(array &$warnings = []): Collection
    {
        $open = ProductionOrder::with(['product', 'productRevision', 'salesOrderLine.salesOrder.customer'])
            ->whereIn('status', ['RELEASED', 'IN_PROGRESS'])->orderBy('required_date')->orderBy('production_order_number')->get();
        $made = $this->piecesMade($open);
        $poured = $this->piecesPoured($open);

        return $open->map(function (ProductionOrder $order) use ($made, $poured, &$warnings) {
            $label = $order->production_order_number.' '.$order->product?->code;
            $revision = $order->productRevision;
            $grade = $revision ? $this->planner->gradeFor($revision) : null;
            $kg = $revision ? $this->planner->metalPerProduct($revision)['kg'] ?? null : null;
            $ordered = (int) round((float) $order->planned_qty);
            $madePieces = (int) ($made[$order->id] ?? 0);
            $pouredPieces = (int) ($poured[$order->id] ?? 0);
            $awaiting = max(0, $madePieces - $pouredPieces);
            $toMould = max(0, $ordered - max($madePieces, $pouredPieces));
            if ($awaiting + $toMould > 0 && ! $grade) {
                $warnings[] = "{$label}: no iron grade is known. Add one to the product.";
            }
            if ($awaiting + $toMould > 0 && ! $kg) {
                $warnings[] = "{$label}: no weight is known. Add a casting weight to the product.";
            }

            return [
                'order' => $order, 'customer' => $order->customerLabel(), 'grade' => $grade, 'kg_per_piece' => $kg, 'due' => $order->required_date,
                'ordered' => $ordered, 'made' => $madePieces, 'poured' => $pouredPieces, 'awaiting' => $awaiting, 'to_mould' => $toMould,
                'plannable' => $grade && $kg,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $orders
     * @return list<array<string, mixed>> One entry per heat, the most urgent first.
     */
    private function heats(Collection $orders, array &$warnings): array
    {
        $heats = [];
        foreach ($orders->filter(fn ($o) => $o['plannable'] && $o['awaiting'] + $o['to_mould'] > 0)->groupBy(fn ($o) => $o['grade']->id) as $rows) {
            $grade = $rows->first()['grade'];
            $furnace = $this->furnaces->eligible($grade)->filter(fn (Furnace $f) => (float) $f->rated_capacity_kg > 0)->sortByDesc(fn ($f) => (float) $f->rated_capacity_kg)->first();
            if (! $furnace) {
                $warnings[] = "No active furnace with a rated capacity can melt {$grade->code}.";

                continue;
            }
            $capacity = (float) $furnace->rated_capacity_kg * (float) config('foundry.planner.furnace_fill_factor');
            $queue = $rows->flatMap(fn ($o) => array_filter([
                $o['awaiting'] > 0 ? ['row' => $o, 'ready' => true, 'left' => $o['awaiting']] : null,
                $o['to_mould'] > 0 ? ['row' => $o, 'ready' => false, 'left' => $o['to_mould']] : null,
            ]))->sortBy([fn ($a, $b) => $b['ready'] <=> $a['ready'], fn ($a, $b) => ($a['row']['due']?->timestamp ?? PHP_INT_MAX) <=> ($b['row']['due']?->timestamp ?? PHP_INT_MAX)])->values()->all();

            while ($queue) {
                $fill = 0.0;
                $lines = [];
                foreach ($queue as &$item) {
                    $perPiece = (float) $item['row']['kg_per_piece'];
                    $pieces = min($item['left'], (int) floor(($capacity - $fill + 0.0001) / $perPiece));
                    if ($pieces <= 0) {
                        continue;
                    }
                    $fill += $pieces * $perPiece;
                    $item['left'] -= $pieces;
                    $lines[] = ['order' => $item['row']['order'], 'customer' => $item['row']['customer'], 'ready' => $item['ready'], 'pieces' => $pieces, 'kg' => round($pieces * $perPiece, 1), 'due' => $item['row']['due']];
                }
                unset($item);
                if (! $lines) {
                    $warnings[] = "A single piece of {$queue[0]['row']['order']->product?->code} is heavier than the working capacity of {$furnace->code}.";
                    break;
                }
                $queue = array_values(array_filter($queue, fn ($i) => $i['left'] > 0));
                $heats[] = $this->heat($grade, $furnace, $capacity, $fill, $lines);
            }
        }

        usort($heats, fn ($a, $b) => [! $a['ready_kg'], $a['due']?->timestamp ?? PHP_INT_MAX] <=> [! $b['ready_kg'], $b['due']?->timestamp ?? PHP_INT_MAX]);

        return $heats;
    }

    /** @return array<string, mixed> */
    private function heat(IronGrade $grade, Furnace $furnace, float $capacity, float $kg, array $lines): array
    {
        $rate = (float) $furnace->nominal_melting_rate_kg_per_hour;

        return [
            'grade' => $grade, 'furnace' => $furnace, 'capacity_kg' => round($capacity, 1), 'kg' => round($kg, 1), 'lines' => $lines,
            'ready_kg' => round(collect($lines)->where('ready', true)->sum('kg'), 1), 'hours' => $rate > 0 ? round($kg / $rate, 1) : null,
            'due' => collect($lines)->pluck('due')->filter()->sortBy->timestamp->first(),
        ];
    }

    /** @return array<string, int> Pieces moulded per order: the quick entries plus runs recorded the full way. */
    private function piecesMade(Collection $orders): array
    {
        $ids = $orders->pluck('id');
        $out = QuickFloorEntry::where('kind', QuickFloorEntry::MADE)->whereIn('production_order_id', $ids)
            ->selectRaw('production_order_id, sum(moulds * pieces_per_mould) as pieces')->groupBy('production_order_id')->pluck('pieces', 'production_order_id')->all();

        $runs = MouldingRun::with('patternRevision')->whereIn('production_order_id', $ids)->whereNotIn('id', QuickFloorEntry::whereNotNull('moulding_run_id')->select('moulding_run_id'))->get();
        foreach ($runs as $run) {
            $perMould = $run->patternRevision ? (int) floor($this->cavities->expectedProductsPerMould($run->patternRevision, $orders->firstWhere('id', $run->production_order_id)->product_id)) : 1;
            $out[$run->production_order_id] = ($out[$run->production_order_id] ?? 0) + $run->moulds_made_qty * max(1, $perMould);
        }

        return $out;
    }

    /** @return array<string, int> */
    private function piecesPoured(Collection $orders): array
    {
        $ids = $orders->pluck('id');
        $out = QuickFloorEntry::where('kind', QuickFloorEntry::POURED)->whereIn('production_order_id', $ids)
            ->selectRaw('production_order_id, sum(moulds * pieces_per_mould) as pieces')->groupBy('production_order_id')->pluck('pieces', 'production_order_id')->all();
        foreach (PourMouldAssembly::whereIn('production_order_id', $ids)->selectRaw('production_order_id, sum(castings_poured_qty) as pieces')->groupBy('production_order_id')->pluck('pieces', 'production_order_id') as $id => $pieces) {
            $out[$id] = ($out[$id] ?? 0) + $pieces;
        }

        return $out;
    }
}
