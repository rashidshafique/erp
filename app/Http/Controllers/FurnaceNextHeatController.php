<?php

namespace App\Http\Controllers;

use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Services\Production\FurnaceFloor;
use App\Support\MeltingCalendar;
use Illuminate\View\View;

/**
 * F4·1, the furnace tablet's next heat: what to melt, split into moulds ready and moulds still to make, and F4·E when
 * there is nothing to melt.
 */
class FurnaceNextHeatController extends Controller
{
    public function index(NextHeatPlan $plan, FurnaceFloor $floor): View
    {
        $built = $plan->build();
        $heats = $built['heats'];
        $next = $heats[0] ?? null;

        return view('furnace.next-heat', [
            'next' => $next,
            'others' => array_slice($heats, 1, 2),
            'openHeats' => $floor->openHeats(),
            'count' => $floor->latestCount(),
            'warnings' => array_values(array_unique($built['warnings'])),
            'calendar' => ['next' => MeltingCalendar::next(), 'description' => MeltingCalendar::describe(), 'enabled' => MeltingCalendar::enabled()],
        ]);
    }
}
