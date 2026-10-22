<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Services\Production\ChargeRecorder;
use App\Services\Production\ChargeSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Charging a heat a recipe round at a time: the start-up (bed) charge, then charge 1, 2, 3 ... of the same recipe. */
class HeatChargeRoundController extends Controller
{
    public function store(Request $request, Heat $heat, ChargeRecorder $recorder): RedirectResponse
    {
        $data = $request->validate([
            'charge_round' => ['required', 'integer', 'between:0,500'],
            'recorded_by' => ['required', 'string', 'max:100'],
            'consumed_at' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.material_id' => ['required', 'uuid', 'exists:materials,id'],
            'lines.*.actual_qty_kg' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'lines.*.material_lot_id' => ['nullable', 'uuid', 'exists:material_lots,id'],
        ]);
        $round = (int) $data['charge_round'];
        $lines = collect($data['lines'])->filter(fn ($l) => (float) ($l['actual_qty_kg'] ?? 0) > 0)->values();

        $recorder->recordRound($heat, $round, $lines, $data['recorded_by'], $data['consumed_at']);

        return redirect()->route('heats.show', $heat)->with('success', ($round === 0 ? 'Start-up charge' : "Charge {$round} of {$heat->chargeRounds()}").' recorded. Inventory has not been deducted.');
    }

    /**
     * Records the round the schedule says is next, with the recipe's quantities, the oldest accepted lot of each material and the
     * current time: the one-click version of the form above. Only the next round can be confirmed.
     */
    public function confirm(Request $request, Heat $heat, ChargeSchedule $schedule, ChargeRecorder $recorder): RedirectResponse
    {
        if (in_array($heat->status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['charge_round' => 'Charges cannot be added to a closed or cancelled heat.']);
        }
        $next = $schedule->for($heat)['next'];
        if (! $next) {
            throw ValidationException::withMessages(['charge_round' => 'Every planned charge is already recorded. Raise the number of planned charges to add more.']);
        }
        $round = $next['round'];
        $lines = $schedule->lines($heat, $round);
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'The recipe sets no quantities for this charge. Enter them on the form below.']);
        }

        $recorder->recordRound($heat, $round, $lines, $request->user()?->name ?? $heat->operator_name ?? 'Operator', now());

        return back()->with('success', ($round === 0 ? 'Start-up charge' : "Charge {$round} of {$heat->chargeRounds()}").' recorded from the recipe. Inventory has not been deducted.');
    }

    /** Adds the charges missing from a heat that is on, worked out from the total metal charged so far. */
    public function fillToTotal(Request $request, Heat $heat, ChargeSchedule $schedule, ChargeRecorder $recorder): RedirectResponse
    {
        $total = (float) $request->validate(['total_charged_kg' => ['required', 'numeric', 'gt:0', 'max:999999999']], ['total_charged_kg.required' => 'Enter the total metal charged so far, in kg.'])['total_charged_kg'];

        $result = DB::transaction(fn () => $schedule->fillToTotal($heat, $total, $request->user()?->name ?? $heat->operator_name ?? 'Operator', $recorder));

        return back()->with('success', "{$result['added']} missing ".($result['added'] === 1 ? 'charge' : 'charges')." added from {$total} kg of metal charged ({$result['charges']} charges), at their scheduled times. Inventory has not been deducted.");
    }

    /** Records every charge that should already have gone in since the heat started, each at its scheduled time. */
    public function backfill(Request $request, Heat $heat, ChargeSchedule $schedule, ChargeRecorder $recorder): RedirectResponse
    {
        if (in_array($heat->status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['charge_round' => 'Charges cannot be added to a closed or cancelled heat.']);
        }
        $heat->loadMissing('materialConsumptions');
        $count = DB::transaction(fn () => $schedule->backfill($heat, $request->user()?->name ?? $heat->operator_name ?? 'Operator', $recorder));
        if ($count === 0) {
            throw ValidationException::withMessages(['charge_round' => 'No charge is due yet, or the heat has not started.']);
        }

        return back()->with('success', "{$count} ".($count === 1 ? 'charge' : 'charges').' filled in from the recipe at their scheduled times. Inventory has not been deducted.');
    }

    /** Changes how many times the recipe is charged: more rounds when the campaign runs on, fewer if it stops early. */
    public function update(Request $request, Heat $heat): RedirectResponse
    {
        $data = $request->validate(['charge_rounds' => ['required', 'integer', 'between:1,500']]);
        $heat->update($data);

        return back()->with('success', "The recipe will be charged {$data['charge_rounds']} time(s) on this heat.");
    }
}
