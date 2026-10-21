<?php

namespace App\Services\Production;

use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Records one charge entry on a heat: the costing rules for purchased lots and internal returns. */
class ChargeRecorder
{
    /**
     * @param  array{consumption_type: string, material_id: string, material_lot_id?: ?string, actual_qty_kg: float|int|string, transfer_cost_per_kg?: float|int|string|null, recorded_by: string, consumed_at: mixed, source_reference?: ?string, notes?: ?string, charge_round?: ?int}  $v
     */
    public function record(Heat $heat, array $v, string $errorKey = 'actual_qty_kg'): HeatMaterialConsumption
    {
        $material = Material::query()->whereKey($v['material_id'])->where('status', 'ACTIVE')->whereNull('deleted_at')->first();
        if (! $material) {
            throw ValidationException::withMessages(['material_id' => 'Select an active material.']);
        }

        $lot = null;
        if ($v['consumption_type'] === 'RAW_MATERIAL') {
            if (empty($v['material_lot_id'])) {
                throw ValidationException::withMessages(['material_lot_id' => "Select an accepted material lot for {$material->name}."]);
            }
            $lot = MaterialLot::query()->whereKey($v['material_lot_id'])->where('material_id', $material->id)->where('status', 'ACCEPTED')->first();
            if (! $lot) {
                throw ValidationException::withMessages(['material_lot_id' => 'The lot must be accepted and belong to the selected material.']);
            }
            if ($lot->unit_cost === null) {
                throw ValidationException::withMessages(['material_lot_id' => 'This lot has no unit cost. Record its cost before charging it.']);
            }
            if (strtoupper((string) $lot->currency_code) !== 'PKR') {
                throw ValidationException::withMessages(['material_lot_id' => 'This costing workflow currently accepts PKR-priced lots only.']);
            }
            $unitCost = (float) $lot->unit_cost;
        } else {
            if (empty($v['transfer_cost_per_kg'])) {
                throw ValidationException::withMessages(['transfer_cost_per_kg' => 'Enter a positive transfer cost for internal returns.']);
            }
            $unitCost = (float) $v['transfer_cost_per_kg'];
        }

        $quantity = (float) $v['actual_qty_kg'];

        return DB::transaction(function () use ($heat, $v, $material, $lot, $unitCost, $quantity, $errorKey) {
            $locked = Heat::query()->whereKey($heat->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['CLOSED', 'CANCELLED'], true)) {
                throw ValidationException::withMessages([$errorKey => 'Charges cannot be added to a closed or cancelled heat.']);
            }
            $sequence = ((int) HeatMaterialConsumption::query()->where('heat_id', $locked->id)->max('sequence')) + 1;

            return HeatMaterialConsumption::create([
                'heat_id' => $locked->id,
                'material_id' => $material->id,
                'material_lot_id' => $lot?->id,
                'sequence' => $sequence,
                'consumption_type' => $v['consumption_type'],
                'source_reference' => $v['source_reference'] ?? null,
                'actual_qty_kg' => $quantity,
                'transfer_cost_per_kg' => $v['consumption_type'] === 'INTERNAL_RETURN' ? $unitCost : null,
                'actual_total_cost' => round($quantity * $unitCost, 4),
                'consumed_at' => $v['consumed_at'],
                'recorded_by' => $v['recorded_by'],
                'notes' => $v['notes'] ?? null,
                'charge_round' => $v['charge_round'] ?? null,
            ]);
        });
    }

    /** The oldest accepted, costed PKR lot of a material: what a tech would normally take from. */
    public function defaultLot(string $materialId): ?MaterialLot
    {
        return MaterialLot::query()->where('material_id', $materialId)->where('status', 'ACCEPTED')->whereNotNull('unit_cost')
            ->where('currency_code', 'PKR')->orderBy('received_date')->orderBy('created_at')->first();
    }
}
