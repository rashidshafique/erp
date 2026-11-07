<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Services\Production\ChargeSchedule;
use App\Services\Production\FurnaceFloor;
use App\Services\Production\HeatEta;
use App\Services\Production\HeatSamples;
use App\Support\Formats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** F4·3, the running heat's charges, and F4·W2 when the furnace has no melting rate to schedule them by. */
class FurnaceHeatController extends Controller
{
    public function current(FurnaceFloor $floor): RedirectResponse
    {
        $heat = $floor->runningHeat();

        return $heat
            ? redirect()->route('furnace.heat.show', $heat)
            : redirect()->route('furnace.next-heat');
    }

    public function show(Heat $heat, FurnaceFloor $floor, ChargeSchedule $schedule, HeatEta $eta, HeatSamples $samples): View
    {
        $heat->loadMissing('furnace', 'ironGrade', 'chargeBatch.items.material', 'materialConsumptions.material');
        $plan = $schedule->for($heat);
        $run = $heat->furnaceRunMinutes();

        return view('furnace.heat', [
            'heat' => $heat,
            'plan' => $plan,
            'big' => $floor->bigStep($heat),
            'balance' => $floor->balance($heat),
            'finish' => $eta->for($heat),
            'runMinutes' => $run,
            'late' => $floor->chargeLateMinutes($heat),
            'planned' => $heat->chargeRounds(),
            'done' => (int) $heat->materialConsumptions->where('charge_round', '>', 0)->pluck('charge_round')->unique()->count(),
            'chargedKg' => $heat->chargedMetalKg(),
            'chargeRows' => $this->chargeRows($heat, $plan),
            'lastSample' => $samples->timeline($heat)->last(),
            'sampleDue' => $samples->due($heat),
            'readings' => $heat->processReadings()->latest('recorded_at')->take(2)->get(),
            'noRate' => $plan['rate'] === null && $heat->chargeBatch !== null,
        ]);
    }

    /**
     * The charges the page lists: the next few, newest first, with what was different when one was recorded at other kilograms.
     *
     * @param  array<string, mixed>  $plan
     * @return Collection<int, array<string, mixed>>
     */
    private function chargeRows(Heat $heat, array $plan): Collection
    {
        $recorded = $heat->materialConsumptions->whereNotNull('charge_round')->groupBy('charge_round');
        $items = $heat->chargeBatch?->items ?? collect();

        return $plan['rounds']->reverse()->take(8)->values()->map(function (array $row) use ($heat, $recorded, $items) {
            $entries = $recorded->get($row['round']);
            $recipe = $items->where('charge_stage', $row['round'] === 0 ? 'BED' : 'ROUND')->keyBy('material_id');
            $parts = [];
            foreach ($entries?->groupBy('material_id') ?? [] as $materialId => $group) {
                $actual = (float) $group->sum('actual_qty_kg');
                $expected = $recipe->has($materialId) ? (float) $heat->chargeKgFor($recipe->get($materialId)) : 0.0;
                if ($expected <= 0 || abs($actual - $expected) > 0.001) {
                    $parts[] = Str::lower((string) ($group->first()->material?->name ?? 'material')).' '.Formats::number($actual, 0);
                }
            }

            return $row + ['differences' => $entries ? ($parts ? implode(' · ', $parts) : 'as recipe') : null, 'entries' => $entries?->sortBy('sequence')];
        });
    }
}
