<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'recorded_by' => ['required', 'string', 'max:100'],
            'consumed_at' => ['required', 'date'],
            'source_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $material = Material::query()
            ->whereKey($validated['material_id'])
            ->where('status', 'ACTIVE')
            ->whereNull('deleted_at')
            ->first();

        if (!$material) {
            throw ValidationException::withMessages([
                'material_id' => 'Select an active material.',
            ]);
        }

        $lot = null;
        $unitCost = null;

        if ($validated['consumption_type'] === 'RAW_MATERIAL') {
            if (empty($validated['material_lot_id'])) {
                throw ValidationException::withMessages([
                    'material_lot_id' => 'Select an accepted material lot for purchased material.',
                ]);
            }

            $lot = MaterialLot::query()
                ->whereKey($validated['material_lot_id'])
                ->where('material_id', $material->id)
                ->where('status', 'ACCEPTED')
                ->first();

            if (!$lot) {
                throw ValidationException::withMessages([
                    'material_lot_id' => 'The lot must be accepted and belong to the selected material.',
                ]);
            }

            if ($lot->unit_cost === null) {
                throw ValidationException::withMessages([
                    'material_lot_id' => 'This lot has no unit cost. Record its cost before charging it.',
                ]);
            }

            if (strtoupper((string) $lot->currency_code) !== 'PKR') {
                throw ValidationException::withMessages([
                    'material_lot_id' => 'This costing workflow currently accepts PKR-priced lots only.',
                ]);
            }

            $unitCost = (float) $lot->unit_cost;
        } else {
            if (empty($validated['transfer_cost_per_kg'])) {
                throw ValidationException::withMessages([
                    'transfer_cost_per_kg' => 'Enter a positive transfer cost for internal returns.',
                ]);
            }

            $unitCost = (float) $validated['transfer_cost_per_kg'];
        }

        $quantity = (float) $validated['actual_qty_kg'];
        $totalCost = round($quantity * $unitCost, 4);

        DB::transaction(function () use (
            $heat,
            $validated,
            $material,
            $lot,
            $unitCost,
            $quantity,
            $totalCost
        ) {
            $lockedHeat = Heat::query()
                ->whereKey($heat->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedHeat->status, ['CLOSED', 'CANCELLED'], true)) {
                throw ValidationException::withMessages([
                    'actual_qty_kg' => 'Charges cannot be added to a closed or cancelled heat.',
                ]);
            }

            $sequence = ((int) HeatMaterialConsumption::query()
                ->where('heat_id', $lockedHeat->id)
                ->max('sequence')) + 1;

            HeatMaterialConsumption::create([
                'heat_id' => $lockedHeat->id,
                'material_id' => $material->id,
                'material_lot_id' => $lot?->id,
                'sequence' => $sequence,
                'consumption_type' => $validated['consumption_type'],
                'source_reference' => $validated['source_reference'] ?? null,
                'actual_qty_kg' => $quantity,
                'transfer_cost_per_kg' => $validated['consumption_type'] === 'INTERNAL_RETURN'
                    ? $unitCost
                    : null,
                'actual_total_cost' => $totalCost,
                'consumed_at' => $validated['consumed_at'],
                'recorded_by' => $validated['recorded_by'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('heats.show', $heat)
            ->with('success', 'Charge entry recorded. Inventory has not been deducted.');
    }
}
