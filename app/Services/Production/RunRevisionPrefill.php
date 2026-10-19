<?php

namespace App\Services\Production;

use App\Models\MouldingRun;
use App\Models\MouldingWorkListItem;
use App\Models\PatternRevision;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;

/**
 * The pattern revision a moulding-run row starts with when none is chosen. In order: the revision planned on that
 * date's and shift's work list, then the revision of the pattern's last completed run, then the one that makes the
 * product of the row's production order, then the pattern's current revision.
 */
class RunRevisionPrefill
{
    public function forRow(string $patternId, ?string $date, ?string $shift, ?string $stationId, ?string $orderId): ?string
    {
        return $this->onWorkList($patternId, $date, $shift, $stationId)
            ?? $this->lastCompletedRun($patternId)
            ?? $this->forOrder($patternId, $orderId)
            ?? PatternRevision::where('pattern_id', $patternId)->where('is_current', true)->value('id');
    }

    /** The work list item for this pattern on that date and shift, at the row's station when it has one. */
    private function onWorkList(string $patternId, ?string $date, ?string $shift, ?string $stationId): ?string
    {
        if (blank($date)) {
            return null;
        }

        return MouldingWorkListItem::query()
            ->whereHas('workList', fn ($q) => $q->whereDate('list_date', $date)
                ->when(filled($shift), fn ($q) => $q->whereRaw('lower(shift) = ?', [mb_strtolower($shift)])))
            ->whereHas('patternRevision', fn ($q) => $q->where('pattern_id', $patternId))
            ->where('status', '!=', 'SKIPPED')
            ->orderByRaw('case when moulding_station_id = ? then 0 else 1 end', [$stationId ?? ''])
            ->orderBy('sequence')
            ->value('pattern_revision_id');
    }

    private function lastCompletedRun(string $patternId): ?string
    {
        return MouldingRun::where('status', 'COMPLETED')
            ->whereIn('pattern_revision_id', PatternRevision::where('pattern_id', $patternId)->select('id'))
            ->orderByDesc('run_date')->orderByDesc('created_at')
            ->value('pattern_revision_id');
    }

    /** The newest revision of the pattern with a cavity for the order's product. */
    private function forOrder(string $patternId, ?string $orderId): ?string
    {
        $productId = $orderId ? ProductionOrder::whereKey($orderId)->value('product_id') : null;
        if (! $productId) {
            return null;
        }

        return PatternRevision::where('pattern_id', $patternId)
            ->whereHas('cavityMap.productRevision', fn ($q) => $q->where('product_id', $productId))
            ->orderByDesc('is_current')->orderByDesc('sequence')
            ->value('id');
    }

    /**
     * Work list revisions for a date, for the grid's own prefill: "date|shift|station|pattern" and "date|shift||pattern" (any station).
     *
     * @return array<string, string>
     */
    public function workListMap(string $date): array
    {
        $map = [];
        $items = MouldingWorkListItem::with('workList', 'patternRevision')
            ->whereHas('workList', fn ($q) => $q->whereDate('list_date', $date))
            ->where('status', '!=', 'SKIPPED')->orderByDesc('sequence')->get();
        foreach ($items as $item) {
            $base = $date.'|'.mb_strtolower((string) $item->workList->shift).'|';
            $pattern = $item->patternRevision?->pattern_id;
            $map[$base.$item->moulding_station_id.'|'.$pattern] = $item->pattern_revision_id;
            $map[$base.'|'.$pattern] = $item->pattern_revision_id;
        }

        return $map;
    }

    /**
     * The revision of each pattern's last completed run.
     *
     * @param  list<string>  $patternIds
     * @return array<string, string> pattern id => revision id
     */
    public function lastRunMap(array $patternIds): array
    {
        return DB::table('moulding_runs as r')
            ->join('pattern_revisions as v', 'v.id', '=', 'r.pattern_revision_id')
            ->where('r.status', 'COMPLETED')->whereIn('v.pattern_id', $patternIds)
            ->orderBy('v.pattern_id')->orderByDesc('r.run_date')->orderByDesc('r.created_at')
            ->selectRaw('distinct on (v.pattern_id) v.pattern_id, r.pattern_revision_id')
            ->pluck('r.pattern_revision_id', 'v.pattern_id')->all();
    }
}
