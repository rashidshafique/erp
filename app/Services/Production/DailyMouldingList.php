<?php

namespace App\Services\Production;

use App\Models\MouldingWorkList;
use App\Models\MouldingWorkListItem;
use App\Services\Planning\OrderPlanner;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The day's moulding list, prepared from open orders: the planner's first shift for that day, spread over the stations, with what
 * is already moulded taken off each order. Rows left unstarted on earlier days' lists are carried over: skipped there, and the new
 * row for the same order and pattern names the one it carries. The list stays a draft to edit until it is released (issued).
 */
class DailyMouldingList
{
    public const SHIFT = 'General';

    public function __construct(private OrderPlanner $planner) {}

    /** The day's open list (draft or released), newest first. */
    public function current(CarbonInterface $day): ?MouldingWorkList
    {
        return MouldingWorkList::whereIn('status', ['DRAFT', 'ISSUED'])->whereDate('list_date', $day)->latest()->first();
    }

    /**
     * The day's open list if there is one; otherwise a new draft from the planner, with earlier unfinished rows carried over.
     *
     * @return array{list: MouldingWorkList, created: bool, carried: int, skipped: list<string>}
     *
     * @throws InvalidArgumentException when no open order needs moulds
     */
    public function prepare(CarbonInterface $day): array
    {
        if ($list = $this->current($day)) {
            return ['list' => $list, 'created' => false, 'carried' => 0, 'skipped' => []];
        }

        return DB::transaction(function () use ($day) {
            $res = $this->planner->fill($day, self::SHIFT);
            $list = $res['list'];
            $list->update(['notes' => 'Prepared from open orders.']);

            return ['list' => $list, 'created' => true, 'carried' => $this->carryOver($list), 'skipped' => $res['skipped']];
        });
    }

    /** Unstarted rows on open lists of earlier days: skipped there, and linked from the new list's row for the same order and pattern. */
    private function carryOver(MouldingWorkList $list): int
    {
        $old = MouldingWorkListItem::with('workList')->where('status', 'PLANNED')->whereNull('moulding_run_id')
            ->whereHas('workList', fn ($q) => $q->whereIn('status', ['DRAFT', 'ISSUED'])->whereDate('list_date', '<', $list->list_date))->get();
        $carried = 0;
        foreach ($old as $item) {
            $row = $list->items()->where('pattern_revision_id', $item->pattern_revision_id)->where('production_order_id', $item->production_order_id)->whereNull('carried_from_item_id')->first();
            $item->update(['status' => 'SKIPPED']);
            if ($row) {
                $row->update(['carried_from_item_id' => $item->id]);
                $carried++;
            }
        }

        return $carried;
    }
}
