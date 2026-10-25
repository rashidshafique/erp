<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Services\Production\ChargeEntryEditor;
use App\Services\Production\ChargeRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HeatMaterialConsumptionController extends Controller
{
    public function store(Request $request, Heat $heat): RedirectResponse
    {
        if (in_array($heat->status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages([
                'actual_qty_kg' => 'Charges cannot be added to a closed or cancelled heat.',
            ]);
        }

        $validated = $request->validate([
            'consumption_type' => ['required', 'in:RAW_MATERIAL,INTERNAL_RETURN'],
            'material_id' => [
                'required',
                'uuid',
                'exists:materials,id',
            ],
            'material_lot_id' => [
                'nullable',
                'uuid',
                'exists:material_lots,id',
            ],
            'actual_qty_kg' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'transfer_cost_per_kg' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:999999999',
            ],
            'charge_round' => ['nullable', 'integer', 'between:0,500'],
            'recorded_by' => ['required', 'string', 'max:100'],
            'consumed_at' => ['required', 'date'],
            'source_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        app(ChargeRecorder::class)->record($heat, $validated + ['charge_round' => $validated['charge_round'] ?? null]);

        return redirect()
            ->route('heats.show', $heat)
            ->with('success', 'Charge entry recorded. Inventory has not been deducted.');
    }

    /** Corrects the weight of a charge entry; its cost follows at the same price per kg. */
    public function update(Request $request, Heat $heat, HeatMaterialConsumption $entry, ChargeEntryEditor $editor): RedirectResponse
    {
        $quantity = (float) $request->validate(['actual_qty_kg' => ['required', 'numeric', 'gt:0', 'max:999999999']])['actual_qty_kg'];
        $entry = $editor->reweigh($heat, $entry, $quantity, $request->user()?->name ?? 'someone');

        return back()->with('success', ($entry->material?->name ?? 'The entry').' is now '.\App\Support\Formats::number($quantity, 3).' kg.');
    }

    /**
     * Removes a charge entry. When it was the last entry of a charge, that charge is marked skipped so the schedule does not add it
     * back by itself; it can be restored.
     */
    public function destroy(Heat $heat, HeatMaterialConsumption $entry, ChargeEntryEditor $editor): RedirectResponse
    {
        $round = $editor->remove($heat, $entry);

        return back()->with('success', 'Charge entry removed.'.($round === null ? '' : ' '.($round === 0 ? 'The start-up charge' : "Charge {$round}").' will not be added automatically; restore it from the Charge tab if it should be.'));
    }

    /** Puts a removed charge back on the schedule, so it is added when due (or by Add back charges). */
    public function restore(Heat $heat, int $round): RedirectResponse
    {
        $heat->update(['skipped_charge_rounds' => array_values(array_diff($heat->skippedChargeRounds(), [$round]))]);

        return back()->with('success', ($round === 0 ? 'The start-up charge' : "Charge {$round}").' is back on the schedule.');
    }
}
