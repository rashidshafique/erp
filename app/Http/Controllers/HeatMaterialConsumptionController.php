<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
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
    public function update(Request $request, Heat $heat, HeatMaterialConsumption $entry): RedirectResponse
    {
        $this->assertEditable($heat, $entry);
        $quantity = (float) $request->validate(['actual_qty_kg' => ['required', 'numeric', 'gt:0', 'max:999999999']])['actual_qty_kg'];

        $before = (float) $entry->actual_qty_kg;
        $unitCost = $entry->materialLot?->unit_cost !== null ? (float) $entry->materialLot->unit_cost
            : ($entry->transfer_cost_per_kg !== null ? (float) $entry->transfer_cost_per_kg
            : ($before > 0 ? (float) $entry->actual_total_cost / $before : (float) $entry->material?->standard_cost_per_uom));
        $entry->update([
            'actual_qty_kg' => $quantity,
            'actual_total_cost' => round($quantity * $unitCost, 4),
            'notes' => trim(($entry->notes ? $entry->notes.' ' : '').'Weight changed from '.\App\Support\Formats::number($before, 3).' kg to '.\App\Support\Formats::number($quantity, 3).' kg by '.($request->user()?->name ?? 'someone').' on '.now()->displayDateTime().'.'),
        ]);

        return back()->with('success', ($entry->material?->name ?? 'The entry').' is now '.\App\Support\Formats::number($quantity, 3).' kg.');
    }

    /**
     * Removes a charge entry. When it was the last entry of a charge, that charge is marked skipped so the schedule does not add it
     * back by itself; it can be restored.
     */
    public function destroy(Heat $heat, HeatMaterialConsumption $entry): RedirectResponse
    {
        $this->assertEditable($heat, $entry);
        $round = $entry->charge_round;
        $entry->delete();

        $note = '';
        if ($round !== null && ! $heat->materialConsumptions()->where('charge_round', $round)->exists()) {
            $heat->update(['skipped_charge_rounds' => array_values(array_unique([...$heat->skippedChargeRounds(), $round]))]);
            $note = ' '.($round === 0 ? 'The start-up charge' : "Charge {$round}").' will not be added automatically; restore it from the Charge tab if it should be.';
        }

        return back()->with('success', 'Charge entry removed.'.$note);
    }

    /** Puts a removed charge back on the schedule, so it is added when due (or by Add back charges). */
    public function restore(Heat $heat, int $round): RedirectResponse
    {
        $heat->update(['skipped_charge_rounds' => array_values(array_diff($heat->skippedChargeRounds(), [$round]))]);

        return back()->with('success', ($round === 0 ? 'The start-up charge' : "Charge {$round}").' is back on the schedule.');
    }

    private function assertEditable(Heat $heat, HeatMaterialConsumption $entry): void
    {
        abort_unless($entry->heat_id === $heat->id, 404);
        if (in_array($heat->status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['actual_qty_kg' => 'Charges cannot be changed on a closed or cancelled heat.']);
        }
        if ($heat->chargePosting()->exists()) {
            throw ValidationException::withMessages(['actual_qty_kg' => 'The charge has been posted to inventory, so its entries can no longer be changed.']);
        }
    }
}
