<?php

namespace App\Services\Production;

use App\Models\CastingLot;
use App\Models\CastingLotStageEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionStageDefinition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Keeps a lot's good and rejected counts, and its production order's totals, in step with what was recorded. */
class LotService
{
    /** Pieces of the lot still in hand: poured less everything rejected so far. */
    public function pieces(CastingLot $lot): int
    {
        return max(0, (int) $lot->quantity_poured - $this->rejected($lot));
    }

    public function rejected(CastingLot $lot): int
    {
        return (int) $lot->stageEvents()->sum('quantity_rejected') + (int) $lot->inspections()->sum('quantity_rejected');
    }

    public function recordStage(CastingLot $lot, ProductionStageDefinition $stage, array $d): CastingLotStageEvent
    {
        return DB::transaction(function () use ($lot, $stage, $d) {
            $lot = CastingLot::whereKey($lot->id)->lockForUpdate()->firstOrFail();
            if (in_array($lot->status, ['CLOSED', 'SCRAPPED', 'DISPATCHED'], true)) {
                throw new InvalidArgumentException("The lot is {$lot->status} and takes no more stage records.");
            }
            if ($stage->code === 'SHAKEOUT') {
                $cool = $this->cooling($lot);
                if ($cool && $cool['wait'] > 0) {
                    if (blank($d['cooling_override_reason'] ?? null)) {
                        throw new InvalidArgumentException("Only {$cool['elapsed']} of the {$cool['minimum']} minutes of cooling since the pour have passed (ready at {$cool['ready_at']->displayShortDateTime()}). Enter a reason to shake out early.");
                    }
                    $d['notes'] = trim(($d['notes'] ?? '')." EARLY SHAKEOUT after {$cool['elapsed']} of {$cool['minimum']} min: ".$d['cooling_override_reason']);
                }
            }
            $in = (int) $d['quantity_in'];
            $acc = (int) $d['quantity_accepted'];
            $rej = (int) $d['quantity_rejected'];
            if ($acc + $rej > $in) {
                throw new InvalidArgumentException('Accepted plus rejected cannot be more than the quantity that came in.');
            }
            $held = $this->pieces($lot);
            if ($in > $held) {
                throw new InvalidArgumentException("Only {$held} piece(s) of the lot are left (poured {$lot->quantity_poured}, rejected so far ".$this->rejected($lot).').');
            }
            $last = $lot->stageEvents()->with('stage')->get()->map(fn ($e) => $e->stage?->sequence)->filter()->max();
            if ($last !== null && $stage->sequence < $last) {
                throw new InvalidArgumentException("This lot has already passed a later stage (sequence {$last}); {$stage->name} cannot be recorded after it.");
            }
            if ($rej > 0 && blank($d['rejection_reason'] ?? null)) {
                throw new InvalidArgumentException('Give a rejection reason when pieces are rejected.');
            }

            $event = CastingLotStageEvent::create([
                'casting_lot_id' => $lot->id, 'stage_definition_id' => $stage->id, 'event_type' => 'COMPLETED', 'status' => 'COMPLETED',
                'quantity_in' => $in, 'quantity_accepted' => $acc, 'quantity_rejected' => $rej, 'quantity_out' => $acc,
                'started_at' => $d['started_at'] ?? null, 'completed_at' => $d['completed_at'] ?? now(),
                'operator_name' => $d['operator_name'] ?? null, 'shift_code' => $d['shift_code'] ?? null,
                'rejection_reason' => $d['rejection_reason'] ?? null, 'notes' => $d['notes'] ?? null,
            ]);
            $this->refresh($lot);
            if ($stage->code === 'SHAKEOUT') {
                app(FlaskManager::class)->shakenOut($lot);   // the mould is knocked out: its flasks go back to the rack
            }

            return $event;
        });
    }

    /** Recalculate counts, status and the order totals for a lot. */
    public function refresh(CastingLot $lot): CastingLot
    {
        $lot = $lot->fresh();
        $rejected = min((int) $lot->quantity_poured, $this->rejected($lot));
        $good = max(0, (int) $lot->quantity_poured - $rejected);
        $status = $lot->status;
        if ($good === 0 && $lot->quantity_poured > 0) {
            $status = 'SCRAPPED';
        } elseif ($status === 'SCRAPPED') {
            $status = 'IN_PRODUCTION';
        }
        $lot->update(['quantity_rejected' => $rejected, 'quantity_good' => $good, 'status' => $status]);
        $this->syncOrder($lot->productionOrder);

        return $lot->fresh();
    }

    /** Order totals: good pieces are those of lots whose final inspection is complete; rejected is all rejects. */
    public function syncOrder(?ProductionOrder $order): void
    {
        if (! $order) {
            return;
        }
        $lots = CastingLot::where('production_order_id', $order->id)->get();
        $released = $lots->filter(fn ($l) => DB::table('quality_inspections')->where('casting_lot_id', $l->id)->where('inspection_type', 'FINAL')->whereIn('status', ['COMPLETED', 'APPROVED', 'CLOSED'])->exists());
        $done = (int) $released->sum('quantity_good');
        $order->update(['completed_qty' => $done, 'rejected_qty' => (int) $lots->sum('quantity_rejected')]);
        if (! \App\Support\OrderRules::completesAtPour() && $order->uom === 'piece' && in_array($order->status, ['RELEASED', 'IN_PROGRESS'], true) && $done >= (float) $order->planned_qty) {
            $order->update(['status' => 'COMPLETED', 'actual_completed_at' => now()]);
        }
    }

    /** Cooling the pattern asks for between pouring and shakeout. @return array{minimum:int, elapsed:int, wait:int, ready_at:\Carbon\CarbonInterface}|null */
    public function cooling(CastingLot $lot): ?array
    {
        $pour = $lot->pourMouldAssembly?->pour;
        $done = $pour?->completed_at;
        $revs = $lot->mouldAssemblies()->map(fn ($m) => $m->mouldingRun?->pattern_revision_id)->filter()->unique()->values();
        $min = $revs->isNotEmpty() ? DB::table('pattern_revisions')->whereIn('id', $revs)->max('min_cooling_minutes') : null;
        if (! $done || $min === null) {
            return null;
        }
        $elapsed = (int) $done->diffInMinutes(now());

        return ['minimum' => (int) $min, 'elapsed' => $elapsed, 'wait' => max(0, (int) $min - $elapsed), 'ready_at' => $done->copy()->addMinutes((int) $min)];
    }
}
