<?php

namespace App\Domains\QuickFlow\Http\Controllers;

use App\Domains\Core\ModuleRegistry;
use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Http\Controllers\Controller;
use App\Models\CastingLot;
use App\Services\Dashboard\DashboardData;
use App\Services\Planning\OrderPlanner;
use App\Services\Quality\QuickInspection;
use App\Models\Heat;
use App\Models\MouldCount;
use App\Services\Production\AfterPour;
use App\Services\Production\DailyMouldingList;
use App\Services\Production\MouldCounter;
use Illuminate\View\View;

/**
 * One screen for a small foundry's day, in the order work flows: orders due, the moulding list, the mould count, the heat, moulds
 * waiting to pour, the work after the pour, castings to check and what is ready to go out. Each card leads straight to the screen that does that step.
 */
class TodayController extends Controller
{
    public function index(DashboardData $dashboard, OrderPlanner $planner, NextHeatPlan $plan, QuickInspection $inspection, ModuleRegistry $modules): View
    {
        $next = $plan->build();
        $toMake = collect($planner->plan(today())['suggestion'])->flatMap(fn ($s) => collect($s['rows'])->map(fn ($r) => $r + ['station' => $s['station']]));
        $toCheck = $inspection->waiting()->flatten(1);
        $dispatch = $modules->isEnabled('dispatch');

        return view('quickflow::today.index', [
            'due' => $dashboard->queue(5),
            'toMake' => $toMake,
            'nextHeat' => $next['heats'][0] ?? null,
            'awaiting' => $next['orders']->filter(fn ($o) => $o['awaiting'] > 0)->values(),
            'toCheck' => $toCheck,
            'piecesToCheck' => (int) $toCheck->sum('to_check'),
            'list' => app(DailyMouldingList::class)->current(today())?->loadCount(['items' => fn ($q) => $q->where('status', '!=', 'SKIPPED')]),
            'mouldsWaiting' => app(MouldCounter::class)->waiting(),
            'lastCount' => MouldCount::latest('counted_at')->value('counted_at'),
            'openHeats' => Heat::whereNotIn('status', ['CLOSED', 'CANCELLED', 'PLANNED'])->get(['id', 'heat_number', 'status']),
            'afterPour' => app(AfterPour::class)->board()->groupBy(fn ($r) => $r['step']->name)->map(fn ($rows) => (int) $rows->sum('pieces')),
            'toPack' => $dispatch ? CastingLot::where('status', 'READY')->count() : null,
            'toDispatch' => $dispatch ? CastingLot::where('status', 'PACKED')->count() : null,
            'attention' => $dashboard->attention(),
        ]);
    }
}
