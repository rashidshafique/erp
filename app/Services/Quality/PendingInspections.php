<?php

namespace App\Services\Quality;

use App\Models\CastingLot;
use App\Models\ProductionStageDefinition;
use App\Models\QualityInspection;
use App\Notifications\AppNotice;
use App\Services\Notifications\Notify;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What is waiting on the inspectors: lots that have come through their last active stage and have no final inspection yet, and
 * inspections that were started and not completed. A lot that becomes available raises one notice to everyone who can inspect.
 */
class PendingInspections
{
    public const PERMISSION = 'quality.inspect';

    /** Final inspections in these states mean the lot is no longer waiting for one. */
    private const FINAL_STARTED = ['IN_PROGRESS', 'COMPLETED', 'APPROVED', 'CLOSED'];

    public function __construct(private Notify $notify) {}

    /** @return Builder<CastingLot> */
    public function awaitingLots(): Builder
    {
        $last = ProductionStageDefinition::where('is_active', true)->max('sequence');

        return CastingLot::query()->where('status', 'IN_PRODUCTION')->where('quantity_good', '>', 0)
            ->when($last === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('casting_lot_stage_events as e')->join('production_stage_definitions as s', 's.id', '=', 'e.stage_definition_id')
                ->whereColumn('e.casting_lot_id', 'casting_lots.id')->where('s.sequence', '>=', $last ?? 0))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('quality_inspections as i')->whereColumn('i.casting_lot_id', 'casting_lots.id')
                ->where('i.inspection_type', 'FINAL')->whereIn('i.status', self::FINAL_STARTED));
    }

    public function inProgress(): int
    {
        return QualityInspection::where('status', 'IN_PROGRESS')->count();
    }

    /** Lots waiting for an inspection plus inspections still open: the number on the menu. */
    public function count(): int
    {
        return $this->awaitingLots()->count() + $this->inProgress();
    }

    /** Tells the inspectors once when a lot has become available for inspection. Returns true when a notice went out. */
    public function announce(CastingLot $lot): bool
    {
        $lot = $lot->fresh();
        if ($lot === null || $lot->inspection_notified_at !== null || ! $this->awaitingLots()->whereKey($lot->id)->exists()) {
            return false;
        }
        $lot->forceFill(['inspection_notified_at' => now()])->save();
        $this->notify->toPermission(self::PERMISSION, new AppNotice(
            'inspection',
            "{$lot->label} is ready for inspection",
            "{$lot->quantity_good} piece(s) have come through the last stage and wait for the final inspection.",
            'info',
            route('lots.inspections.create', $lot),
        ));

        return true;
    }
}
