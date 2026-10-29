<?php

namespace App\Services\Production;

use App\Models\CastingLot;
use App\Models\ProductionStageDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The work between the pour and quality control: the active steps before the first inspection on the route (knock-out, fettling,
 * shot blast and the like). Castings move through them in batches, as many or as few as the floor did ("20 moulds of the drain
 * waste fettled"), or all at once. A batch is shared over the open lots of the product oldest first, so a lot can sit at two steps.
 */
class AfterPour
{
    public function __construct(private LotService $lots) {}

    /** @return Collection<int, ProductionStageDefinition> the steps before the first inspection that apply to the product revision, in order */
    public function stepsFor(?string $productRevisionId): Collection
    {
        $all = app(ProcessRoutes::class)->stepsFor($productRevisionId);
        $firstCheck = $all->search(fn ($s) => $s->kind === 'INSPECTION' || $s->is_quality_gate);

        return ($firstCheck === false ? $all : $all->take($firstCheck))->values();
    }

    /**
     * Pieces of the lot waiting at each step, and those through every step (ready for inspection).
     *
     * @return array{waiting: array<string, int>, done: int}
     */
    public function progress(CastingLot $lot): array
    {
        $events = $lot->relationLoaded('stageEvents') ? $lot->stageEvents : $lot->stageEvents()->get();
        $available = (int) $lot->quantity_poured;
        $waiting = [];
        foreach ($this->stepsFor($lot->product_revision_id) as $step) {
            $at = $events->where('stage_definition_id', $step->id);
            $waiting[$step->id] = max(0, $available - (int) $at->sum('quantity_in'));
            $available = (int) $at->sum('quantity_accepted');
        }

        return ['waiting' => $waiting, 'done' => $available];
    }

    /** Pieces a mould of the lot gave: what was poured over the moulds it came from. */
    public function piecesPerMould(CastingLot $lot): float
    {
        $moulds = (int) DB::table('casting_lot_pour_mould_assembly')->where('casting_lot_id', $lot->id)->count() ?: ($lot->pour_mould_assembly_id ? 1 : 0);

        return $moulds > 0 ? (int) $lot->quantity_poured / $moulds : 0.0;
    }

    /**
     * What waits at each step, by product: pieces, moulds, the lots, the oldest pour and, at a knock-out step, when cooling ends.
     *
     * @return Collection<int, array{step: ProductionStageDefinition, product_revision_id: string, product: mixed, pieces: int, moulds: ?int, lots: Collection<int, CastingLot>, oldest: mixed, ready_at: mixed, on_hold: int}>
     */
    public function board(): Collection
    {
        $lots = CastingLot::with(['product', 'productRevision', 'stageEvents', 'pourMouldAssembly.pour'])->whereIn('status', ['IN_PRODUCTION', 'ON_HOLD'])->orderBy('created_at')->get();
        $rows = [];
        foreach ($lots as $lot) {
            $steps = $this->stepsFor($lot->product_revision_id)->keyBy('id');
            foreach ($this->progress($lot)['waiting'] as $stepId => $n) {
                if ($n < 1) {
                    continue;
                }
                $key = $stepId.'|'.$lot->product_revision_id;
                $rows[$key] ??= ['step' => $steps[$stepId], 'product_revision_id' => $lot->product_revision_id, 'product' => $lot->product, 'pieces' => 0, 'moulds' => 0.0,
                    'lots' => collect(), 'oldest' => $lot->pourMouldAssembly?->pour?->completed_at ?? $lot->created_at, 'ready_at' => null, 'on_hold' => 0];
                if ($lot->status === 'ON_HOLD') {
                    $rows[$key]['on_hold'] += $n;

                    continue;
                }
                $per = $this->piecesPerMould($lot);
                $rows[$key]['pieces'] += $n;
                $rows[$key]['moulds'] = $per > 0 && $rows[$key]['moulds'] !== null ? $rows[$key]['moulds'] + $n / $per : null;
                $rows[$key]['lots']->push($lot);
                if ($steps[$stepId]->knocks_out_mould && ($cool = $this->lots->cooling($lot)) && $cool['wait'] > 0) {
                    $rows[$key]['ready_at'] = max($rows[$key]['ready_at'] ?? $cool['ready_at'], $cool['ready_at']);
                }
            }
        }

        return collect($rows)->map(fn ($r) => ['moulds' => $r['moulds'] === null ? null : (int) round($r['moulds'])] + $r)
            ->sortBy(fn ($r) => sprintf('%05d %s', $r['step']->sequence, $r['oldest']))->values();
    }

    /**
     * Records a batch at a step for a product: pieces (or moulds) from its open lots oldest first, with rejects on the first lots.
     *
     * @param  array{quantity?: ?int, unit?: string, rejected?: int, rejection_reason?: ?string, operator_name?: ?string, cooling_override_reason?: ?string}  $d  no quantity moves all that waits
     * @return array{pieces: int, lots: int}
     *
     * @throws InvalidArgumentException
     */
    public function record(ProductionStageDefinition $step, string $productRevisionId, array $d): array
    {
        $lots = CastingLot::with('stageEvents')->where('product_revision_id', $productRevisionId)->where('status', 'IN_PRODUCTION')->orderBy('created_at')->get()
            ->map(fn ($lot) => ['lot' => $lot, 'waiting' => $this->progress($lot)['waiting'][$step->id] ?? 0])->filter(fn ($r) => $r['waiting'] > 0)->values();
        $total = (int) $lots->sum('waiting');
        if ($total === 0) {
            throw new InvalidArgumentException("Nothing waits at {$step->name} for this product.");
        }
        $byMoulds = ($d['unit'] ?? 'PIECES') === 'MOULDS';
        $left = filled($d['quantity'] ?? null) ? (int) $d['quantity'] : null;
        $rejectLeft = (int) ($d['rejected'] ?? 0);
        if ($rejectLeft > 0 && blank($d['rejection_reason'] ?? null)) {
            throw new InvalidArgumentException('Give a reason for the rejected pieces.');
        }

        return DB::transaction(function () use ($lots, $step, $d, $byMoulds, $left, $rejectLeft, $total) {
            [$pieces, $touched] = [0, 0];
            foreach ($lots as ['lot' => $lot, 'waiting' => $waiting]) {
                if ($left !== null && $left < 1) {
                    break;
                }
                $per = $this->piecesPerMould($lot);
                if ($byMoulds && $per <= 0) {
                    throw new InvalidArgumentException("Lot {$lot->lot_number} has no moulds recorded. Enter pieces instead.");
                }
                $take = $left === null ? $waiting : min($waiting, $byMoulds ? (int) round($left * $per) : $left);
                $rej = min($rejectLeft, $take);
                $this->lots->recordStage($lot, $step, [
                    'quantity_in' => $take, 'quantity_accepted' => $take - $rej, 'quantity_rejected' => $rej, 'rejection_reason' => $rej > 0 ? $d['rejection_reason'] : null,
                    'operator_name' => $d['operator_name'] ?? null, 'cooling_override_reason' => $d['cooling_override_reason'] ?? null, 'batch' => true,
                ]);
                $left = $left === null ? null : $left - ($byMoulds ? (int) round($take / $per) : $take);
                [$rejectLeft, $pieces, $touched] = [$rejectLeft - $rej, $pieces + $take, $touched + 1];
            }
            if ($left !== null && $left > 0) {
                throw new InvalidArgumentException("Only {$total} pieces wait at {$step->name} for this product.");
            }
            if ($rejectLeft > 0) {
                throw new InvalidArgumentException('More pieces rejected than were in the batch.');
            }

            return ['pieces' => $pieces, 'lots' => $touched];
        });
    }
}
