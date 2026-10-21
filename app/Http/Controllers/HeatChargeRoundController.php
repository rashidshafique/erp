<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Services\Production\ChargeRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Enter a quantity for at least one material.']);
        }
        if (HeatMaterialConsumption::where('heat_id', $heat->id)->where('charge_round', $round)->exists()) {
            throw ValidationException::withMessages(['charge_round' => ($round === 0 ? 'The start-up charge' : "Charge {$round}").' is already recorded on this heat.']);
        }

        DB::transaction(function () use ($heat, $recorder, $data, $lines, $round) {
            foreach ($lines as $line) {
                $lotId = $line['material_lot_id'] ?? $recorder->defaultLot($line['material_id'])?->id;
                $recorder->record($heat, [
                    'consumption_type' => 'RAW_MATERIAL', 'material_id' => $line['material_id'], 'material_lot_id' => $lotId,
                    'actual_qty_kg' => $line['actual_qty_kg'], 'recorded_by' => $data['recorded_by'], 'consumed_at' => $data['consumed_at'],
                    'charge_round' => $round,
                ], 'lines');
            }
        });

        return redirect()->route('heats.show', $heat)->with('success', ($round === 0 ? 'Start-up charge' : "Charge {$round} of {$heat->chargeRounds()}").' recorded. Inventory has not been deducted.');
    }

    /** Changes how many times the recipe is charged: more rounds when the campaign runs on, fewer if it stops early. */
    public function update(Request $request, Heat $heat): RedirectResponse
    {
        $data = $request->validate(['charge_rounds' => ['required', 'integer', 'between:1,500']]);
        $heat->update($data);

        return back()->with('success', "The recipe will be charged {$data['charge_rounds']} time(s) on this heat.");
    }
}
