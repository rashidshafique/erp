<?php

namespace App\Domains\Tooling\Services;

use App\Domains\Tooling\Models\ToolingUsageRecord;
use App\Domains\Tooling\Support\CoreRules;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\CoreLot;
use App\Models\CoreLotUse;
use App\Models\CoreRequirement;
use App\Models\MouldingRun;
use App\Models\PatternRevisionCavity;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Making cores, keeping them and taking them out, with the count each core box has made. */
class CoreLotService
{
    /**
     * Records a batch of cores made from a box. The box's life count goes up by the cores made.
     */
    public function make(CoreDefinition $core, CoreBox $box, int $quantity, CarbonInterface $madeOn, ?string $location = null, ?string $notes = null, bool $usedAtOnce = false): CoreLot
    {
        if ($box->status !== 'ACTIVE') {
            throw ValidationException::withMessages(['core_box_id' => "{$box->code} is not in service."]);
        }
        if (! $box->cavityMap()->where('core_definition_id', $core->id)->exists()) {
            throw ValidationException::withMessages(['core_box_id' => "{$box->code} has no cavity that makes {$core->name}."]);
        }
        if (blank($location) && ! $usedAtOnce && CoreRules::get('location') === 'REQUIRED') {
            throw ValidationException::withMessages(['location' => 'Say where the cores are kept. If they are used straight away, record them as used instead.']);
        }

        return DB::transaction(function () use ($core, $box, $quantity, $madeOn, $location, $notes) {
            $lot = CoreLot::create([
                'lot_number' => $this->nextNumber($madeOn),
                'core_definition_id' => $core->id,
                'core_box_id' => $box->id,
                'quantity_made' => $quantity,
                'made_on' => $madeOn,
                'location' => filled($location) ? $location : null,
                'status' => filled($location) ? 'STORED' : 'MADE',
                'qc_status' => CoreRules::get('qc') === 'REQUIRED' ? 'PENDING' : 'NOT_REQUIRED',
                'notes' => $notes,
            ]);
            ToolingUsageRecord::create([
                'tooling_type' => 'CORE_BOX', 'core_box_id' => $box->id, 'usage_quantity' => $quantity, 'usage_unit' => 'CORES',
                'used_at' => $madeOn->copy()->startOfDay(), 'reference_type' => 'core_lot', 'reference_id' => $lot->id,
            ]);

            return $lot;
        });
    }

    /** Puts a lot on a shelf, or moves it. */
    public function store(CoreLot $lot, string $location): CoreLot
    {
        $lot->update(['location' => $location, 'status' => $lot->remaining() > 0 ? 'STORED' : 'USED_UP']);

        return $lot;
    }

    /** Passes or fails a lot. A failed lot cannot be issued; the cores can still be scrapped. */
    public function check(CoreLot $lot, bool $passed, ?string $notes = null): CoreLot
    {
        $lot->update(['qc_status' => $passed ? 'PASSED' : 'FAILED', 'qc_notes' => $notes]);

        return $lot;
    }

    /**
     * Takes cores out of a lot. Using needs a lot that has passed its check when checks are on; scrapping never does.
     */
    public function take(CoreLot $lot, int $quantity, string $kind, CarbonInterface $on, ?string $mouldingRunId = null, ?string $notes = null): CoreLotUse
    {
        return DB::transaction(function () use ($lot, $quantity, $kind, $on, $mouldingRunId, $notes) {
            $lot = CoreLot::whereKey($lot->id)->lockForUpdate()->firstOrFail();
            if ($kind === 'USED' && ! $lot->isIssuable()) {
                throw ValidationException::withMessages(['quantity' => "Lot {$lot->lot_number} has not passed its check and cannot be used."]);
            }
            if ($quantity > $lot->remaining()) {
                throw ValidationException::withMessages(['quantity' => "Only {$lot->remaining()} left in lot {$lot->lot_number}."]);
            }
            $use = $lot->uses()->create(['quantity' => $quantity, 'kind' => $kind, 'used_on' => $on, 'moulding_run_id' => $kind === 'USED' ? $mouldingRunId : null, 'notes' => $notes]);
            if ($lot->remaining() === 0) {
                $lot->update(['status' => 'USED_UP']);
            }

            return $use;
        });
    }

    /**
     * Cores on hand by core, counting only lots that can be issued.
     *
     * @return array<string, int> core id => cores
     */
    public function onHand(): array
    {
        $made = CoreLot::whereIn('qc_status', ['NOT_REQUIRED', 'PASSED'])->selectRaw('core_definition_id, sum(quantity_made) as n')->groupBy('core_definition_id')->pluck('n', 'core_definition_id');
        $taken = CoreLotUse::join('core_lots', 'core_lots.id', '=', 'core_lot_uses.core_lot_id')->whereIn('core_lots.qc_status', ['NOT_REQUIRED', 'PASSED'])
            ->selectRaw('core_lots.core_definition_id as core_id, sum(core_lot_uses.quantity) as n')->groupBy('core_lots.core_definition_id')->pluck('n', 'core_id');

        return $made->map(fn ($n, $id) => max(0, (int) $n - (int) ($taken[$id] ?? 0)))->all();
    }

    /**
     * The cores a moulding run's moulds need: per mould from the pattern's cavities (the cavities the run uses) and the cores each product needs.
     *
     * @return list<array{core: CoreDefinition, per_mould: int, needed: int, issued: int, on_hand: int, short: int}>
     */
    public function needsForRun(MouldingRun $run): array
    {
        if (! $run->pattern_revision_id) {
            return [];
        }
        $cavities = PatternRevisionCavity::where('pattern_revision_id', $run->pattern_revision_id)->where('is_closed', false)
            ->when(! empty($run->cavity_numbers), fn ($q) => $q->whereIn('cavity_number', $run->cavity_numbers))
            ->whereNotNull('product_revision_id')->get();
        $perMould = CoreRequirement::whereIn('product_revision_id', $cavities->pluck('product_revision_id'))->get()
            ->groupBy('core_definition_id')
            ->map(fn ($needs) => $needs->sum(fn ($n) => $n->quantity_per_casting * $cavities->where('product_revision_id', $n->product_revision_id)->count()));
        if ($perMould->isEmpty()) {
            return [];
        }
        $moulds = max((int) $run->planned_mould_qty, (int) $run->moulds_made_qty);
        $onHand = $this->onHand();
        $issued = CoreLotUse::where('moulding_run_id', $run->id)->where('kind', 'USED')
            ->join('core_lots', 'core_lots.id', '=', 'core_lot_uses.core_lot_id')
            ->selectRaw('core_lots.core_definition_id as core_id, sum(core_lot_uses.quantity) as n')->groupBy('core_lots.core_definition_id')->pluck('n', 'core_id');

        return CoreDefinition::withTrashed()->whereIn('id', $perMould->keys())->orderBy('code')->get()->map(function (CoreDefinition $core) use ($perMould, $moulds, $onHand, $issued) {
            $needed = $perMould[$core->id] * $moulds;
            $done = (int) ($issued[$core->id] ?? 0);

            return ['core' => $core, 'per_mould' => (int) $perMould[$core->id], 'needed' => $needed, 'issued' => $done, 'on_hand' => $onHand[$core->id] ?? 0,
                'short' => max(0, $needed - $done - ($onHand[$core->id] ?? 0))];
        })->all();
    }

    /** Issues cores to a run from the oldest lots first. Nothing is issued if the lots that can be used hold fewer than asked. */
    public function issueToRun(CoreDefinition $core, int $quantity, MouldingRun $run, ?CarbonInterface $on = null): int
    {
        return DB::transaction(function () use ($core, $quantity, $run, $on) {
            $left = $quantity;
            $lots = CoreLot::where('core_definition_id', $core->id)->whereIn('qc_status', ['NOT_REQUIRED', 'PASSED'])->where('status', '!=', 'USED_UP')->orderBy('made_on')->orderBy('lot_number')->lockForUpdate()->get();
            foreach ($lots as $lot) {
                $take = min($left, $lot->remaining());
                if ($take > 0) {
                    $this->take($lot, $take, 'USED', $on ?? today(), $run->id);
                    $left -= $take;
                }
                if ($left === 0) {
                    break;
                }
            }
            if ($left > 0) {
                throw ValidationException::withMessages(['quantity' => "Only ".($quantity - $left)." of {$core->name} can be issued: the rest are not made or have not passed their check."]);
            }

            return $quantity;
        });
    }

    private function nextNumber(CarbonInterface $madeOn): string
    {
        $prefix = 'CL-'.$madeOn->format('ymd').'-';
        $n = CoreLot::where('lot_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }
}
