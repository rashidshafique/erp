<?php

namespace App\Domains\QuickFlow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\MouldingWorkListItem;
use App\Models\PatternRevision;
use App\Services\Production\DailyMouldingList;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The day's moulding list on the quick screens: prepared from open orders (or by the evening job), changed row by row, then
 * released to the floor. Moulds made are typed into the quick grid, which starts from the released list's rows.
 */
class MouldingListController extends Controller
{
    public function index(Request $request, DailyMouldingList $lists): View
    {
        $day = Carbon::parse($request->validate(['day' => ['nullable', 'date']])['day'] ?? today());
        $list = $lists->current($day)?->load(['items' => fn ($q) => $q->where('status', '!=', 'SKIPPED')->orderBy('sequence'),
            'items.station', 'items.patternRevision.pattern', 'items.productionOrder.product', 'items.carriedFrom.workList']);

        return view('quickflow::list.index', [
            'day' => $day, 'list' => $list,
            'made' => $list ? $this->madeSoFar($list->items, $day) : [],
            'stations' => MouldingStation::where('status', 'ACTIVE')->orderBy('code')->get(),
            'patterns' => PatternRevision::with('pattern')->where('status', 'APPROVED')->whereHas('pattern', fn ($q) => $q->whereNull('deleted_at'))->get()->sortBy('pattern.code'),
        ]);
    }

    /**
     * Moulds accepted for each row's order and pattern from the list's day on.
     *
     * @param  \Illuminate\Support\Collection<int, MouldingWorkListItem>  $items
     * @return array<string, int> item id => moulds
     */
    private function madeSoFar($items, Carbon $day): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[$item->id] = (int) MouldingRun::where('pattern_revision_id', $item->pattern_revision_id)->where('production_order_id', $item->production_order_id)
                ->where('status', '!=', 'CANCELLED')->whereDate('created_at', '>=', $day)->sum('accepted_mould_qty');
        }

        return $out;
    }
}
