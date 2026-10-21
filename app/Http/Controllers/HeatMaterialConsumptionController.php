<?php

namespace App\Http\Controllers;

use App\Models\Heat;
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
}
