<?php

namespace App\Http\Controllers;

use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Models\ChargeBatch;
use App\Models\Furnace;
use App\Models\IronGrade;
use App\Services\FurnaceEligibility;
use App\Services\Planning\HeatPlanner;
use App\Services\Production\FurnaceFloor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * F4·2, starting the next heat on the furnace's own recipe: a kilogram changed for this heat only, then blast on now.
 * F4·W warns when another furnace still has a heat open; it never blocks a different furnace.
 */
class FurnaceStartHeatController extends Controller
{
    public function create(Request $request, NextHeatPlan $plan, FurnaceFloor $floor): View|RedirectResponse
    {
        $heats = $plan->build()['heats'];
        $index = max(0, (int) $request->query('heat', 0));
        $next = $heats[$index] ?? null;
        if (! $next) {
            return redirect()->route('floor.furnace.next-heat')->with('error', 'There is nothing to melt.');
        }

        $other = $floor->openHeats($next['furnace']->id);
        if ($other->isNotEmpty() && ! $request->boolean('anyway')) {
            return view('floor.furnace.previous-open', ['next' => $next, 'other' => $other->first(), 'index' => $index]);
        }

        $recipes = ChargeBatch::with('items.material')
            ->where('status', 'ACTIVE')
            ->where('process_type', $next['furnace']->furnace_type)
            ->orderBy('code')
            ->get();
        $recipe = $recipes->firstWhere('id', $request->query('recipe_id'))
            ?? $recipes->firstWhere('id', $next['furnace']->default_charge_batch_id)
            ?? $recipes->first();

        $perCharge = $recipe ? $recipe->metalPerChargeKg() : 0.0;
        $rate = (float) $next['furnace']->nominal_melting_rate_kg_per_hour;

        return view('floor.furnace.start-heat', [
            'next' => $next,
            'index' => $index,
            'recipes' => $recipes,
            'recipe' => $recipe,
            'perCharge' => $perCharge,
            'charges' => $perCharge > 0 ? (int) ceil((float) $next['kg'] / $perCharge) : null,
            'interval' => $perCharge > 0 && $rate > 0 ? (int) max(1, round($perCharge / $rate * 60)) : null,
            'rate' => $rate,
        ]);
    }

    public function store(Request $request, HeatPlanner $planner, FurnaceEligibility $eligibility): RedirectResponse
    {
        $data = $request->validate([
            'furnace_id' => ['required', 'uuid', Rule::exists('furnaces', 'id')->where('status', 'ACTIVE')],
            'iron_grade_id' => ['required', 'uuid', 'exists:iron_grades,id'],
            'kg' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'recipe_id' => ['nullable', 'uuid', 'exists:charge_batches,id'],
            'charge_kg' => ['nullable', 'array', 'max:100'],
            'charge_kg.*' => ['nullable', 'numeric', 'gte:0', 'max:999999'],
            'light_only' => ['nullable', 'boolean'],
        ]);

        $furnace = Furnace::findOrFail($data['furnace_id']);
        $grade = IronGrade::findOrFail($data['iron_grade_id']);
        if (! $eligibility->canMelt($furnace, $grade)) {
            return back()->withErrors(['furnace_id' => $eligibility->reason($furnace, $grade)])->withInput();
        }

        // The recipe picked, else the furnace's own default; either must be active and for this furnace type.
        $recipe = ChargeBatch::with('items')->where('status', 'ACTIVE')->find($data['recipe_id'] ?? $furnace->default_charge_batch_id);
        if (filled($data['recipe_id'] ?? null) && (! $recipe || $recipe->process_type !== $furnace->furnace_type)) {
            return back()->withErrors(['recipe_id' => 'That recipe is not for this furnace type.'])->withInput();
        }
        $recipe = $recipe && $recipe->process_type === $furnace->furnace_type ? $recipe : null;

        // Only the kilograms that differ from the recipe are kept, on this heat, keyed by the recipe item they change.
        $overrides = [];
        foreach ($recipe?->items ?? collect() as $item) {
            $kg = $data['charge_kg'][$item->id] ?? null;
            if ($kg !== null && abs((float) $kg - (float) $item->perChargeKg()) > 0.0005) {
                $overrides[$item->id] = (float) $kg;
            }
        }

        $heat = $planner->startHeat(
            $furnace, $grade, (float) $data['kg'], today(), $data['operator_name'] ?? null,
            'Started from the furnace tablet.',
            ['recipe' => $recipe, 'overrides' => $overrides, 'start' => true, 'start_at' => now(), 'blast_on' => ! ($data['light_only'] ?? false)],
        );

        return redirect()->route('floor.furnace.heat.show', $heat)
            ->with('success', "Heat {$heat->heat_number} started. The moulders are asked to count what is ready.");
    }
}
