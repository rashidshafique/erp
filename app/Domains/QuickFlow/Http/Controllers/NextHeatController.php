<?php

namespace App\Domains\QuickFlow\Http\Controllers;

use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Http\Controllers\Controller;
use App\Models\Furnace;
use App\Models\IronGrade;
use App\Services\FurnaceEligibility;
use App\Services\Planning\HeatPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NextHeatController extends Controller
{
    public function index(NextHeatPlan $plan): View
    {
        return view('quickflow::heat.index', $plan->build());
    }

    /** Starts the heat as planned, then goes to it for thermal tests. The weight can be changed before it starts. */
    public function start(Request $request, HeatPlanner $planner, FurnaceEligibility $eligibility): RedirectResponse
    {
        $data = $request->validate([
            'iron_grade_id' => ['required', 'uuid', 'exists:iron_grades,id'], 'furnace_id' => ['required', 'uuid', Rule::exists('furnaces', 'id')->where('status', 'ACTIVE')],
            'kg' => ['required', 'numeric', 'gt:0', 'max:1000000'], 'operator_name' => ['nullable', 'string', 'max:100'],
        ]);
        $furnace = Furnace::findOrFail($data['furnace_id']);
        $grade = IronGrade::findOrFail($data['iron_grade_id']);
        if (! $eligibility->canMelt($furnace, $grade)) {
            return back()->withErrors(['furnace_id' => $eligibility->reason($furnace, $grade)]);
        }
        $heat = $planner->startHeat($furnace, $grade, (float) $data['kg'], today(), $data['operator_name'] ?? null, 'Started from the next heat plan.');

        return redirect()->route('heats.show', $heat)->with('success', "Heat {$heat->heat_number} started. Record thermal tests on it as it runs.");
    }
}
