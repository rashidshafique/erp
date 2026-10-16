<?php

namespace App\Http\Controllers;

use App\Models\Furnace;
use App\Models\PlannedHeat;
use App\Services\FurnaceEligibility;
use App\Services\Planning\HeatPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class HeatPlanController extends Controller
{
    public function index(): View
    {
        return view('heat-plan.index', [
            'heats' => PlannedHeat::with(['ironGrade', 'furnace', 'orders.productionOrder.product', 'heat'])
                ->where('status', '!=', 'CANCELLED')->orderBy('planned_date')->orderBy('sequence')->get(),
            'furnaces' => Furnace::where('status', 'ACTIVE')->orderBy('code')->get(),
            'skipped' => session('plan_skipped', []),
        ]);
    }

    public function rebuild(Request $request, HeatPlanner $planner): RedirectResponse
    {
        $data = $request->validate(['from' => ['required', 'date', 'after_or_equal:today']]);
        $res = $planner->rebuild(\Carbon\Carbon::parse($data['from']));

        return redirect()->route('heat-plan.index')->with('success', "Plan rebuilt: {$res['heats']} heat(s).")->with('plan_skipped', $res['skipped']);
    }

    public function assign(Request $request, PlannedHeat $plannedHeat, FurnaceEligibility $eligibility): RedirectResponse
    {
        $data = $request->validate(['furnace_id' => ['required', 'uuid', 'exists:furnaces,id']]);
        abort_unless($plannedHeat->status === 'PLANNED', 404);
        $furnace = Furnace::findOrFail($data['furnace_id']);
        if (! $eligibility->canMelt($furnace, $plannedHeat->ironGrade)) {
            return back()->withErrors(['furnace_id' => $eligibility->reason($furnace, $plannedHeat->ironGrade)]);
        }
        $plannedHeat->update(['furnace_id' => $furnace->id]);

        return back()->with('success', 'Furnace changed.');
    }

    public function release(Request $request, PlannedHeat $plannedHeat, HeatPlanner $planner): RedirectResponse
    {
        $data = $request->validate(['operator_name' => ['nullable', 'string', 'max:100']]);
        try {
            $heat = $planner->release($plannedHeat, $data['operator_name'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['release' => $e->getMessage()]);
        }

        return redirect()->route('heats.show', $heat)->with('success', "Heat {$heat->heat_number} released from the plan.");
    }
}
