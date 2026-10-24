<?php

namespace App\Support;

use App\Domains\Core\Contracts\MenuFilter;
use App\Models\MouldAssembly;
use App\Models\MouldingRun;
use App\Models\NonconformanceReport;
use App\Models\Pour;
use App\Models\ProductionOrder;
use App\Services\Quality\PendingInspections;

/**
 * What waits on someone behind the core menu links, for the count pills in the sidebar. Module links carry their own
 * 'count' closure; these are the links the layout draws itself.
 */
class MenuCounts
{
    /** Production links. */
    public const PRODUCTION = ['production-orders.index', 'moulding-runs.index', 'pours.index', 'mould-readiness.index'];

    /** Quality links. */
    public const QUALITY = ['inspections.index', 'ncrs.index'];

    /** @return array<string, int> route name => count, for the links this menu shows */
    public function forMenu(MenuFilter $menu): array
    {
        $counts = [
            'production-orders.index' => fn () => $this->lateProductionOrders(),
            'moulding-runs.index' => fn () => $this->runsInProgress(),
            'pours.index' => fn () => $this->openPours(),
            'mould-readiness.index' => fn () => $this->mouldsWaitingToPour(),
            'inspections.index' => fn () => app(PendingInspections::class)->count(),
            'ncrs.index' => fn () => $this->openNonconformances(),
        ];

        return array_map(fn ($count) => $count(), array_filter($counts, fn ($route) => $menu->shows($route), ARRAY_FILTER_USE_KEY));
    }

    /** @param  array<string, int>  $counts  @param  list<string>  $routes */
    public static function sum(array $counts, array $routes): int
    {
        return array_sum(array_intersect_key($counts, array_flip($routes)));
    }

    public function lateProductionOrders(): int
    {
        return ProductionOrder::open()->whereDate('required_date', '<', today())->count();
    }

    public function runsInProgress(): int
    {
        return MouldingRun::where('status', 'IN_PROGRESS')->count();
    }

    public function openPours(): int
    {
        return Pour::whereIn('status', ['PLANNED', 'IN_PROGRESS'])->count();
    }

    /** Moulds assembled and not yet on a pour. */
    public function mouldsWaitingToPour(): int
    {
        return MouldAssembly::where('status', 'PREPARED')->whereDoesntHave('pourAssignment')->count();
    }

    public function openNonconformances(): int
    {
        return NonconformanceReport::where('status', '!=', 'CLOSED')->count();
    }
}
