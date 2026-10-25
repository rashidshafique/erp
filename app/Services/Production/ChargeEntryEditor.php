<?php

namespace App\Services\Production;

use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Support\Formats;
use Illuminate\Validation\ValidationException;

/**
 * Corrects or removes a charge entry on a heat, for the charge history screen. Entries are fixed once the heat is closed or its
 * charge has been posted to inventory. Removing the last entry of a charge marks that charge skipped, so the schedule does not
 * add it back by itself.
 */
class ChargeEntryEditor
{
    /** Whether the heat's entries can still be changed. */
    public function open(Heat $heat): bool
    {
        return ! in_array($heat->status, ['CLOSED', 'CANCELLED'], true) && ! $heat->chargePosting()->exists();
    }

    /** Sets the weight of an entry; its cost follows at the same price per kg. */
    public function reweigh(Heat $heat, HeatMaterialConsumption $entry, float $quantity, string $by): HeatMaterialConsumption
    {
        $this->assertEditable($heat, $entry);
        if ($quantity <= 0 || $quantity > 999999999) {
            throw ValidationException::withMessages(['actual_qty_kg' => 'Enter a weight above zero.']);
        }
        $before = (float) $entry->actual_qty_kg;
        $unitCost = $entry->materialLot?->unit_cost !== null ? (float) $entry->materialLot->unit_cost
            : ($entry->transfer_cost_per_kg !== null ? (float) $entry->transfer_cost_per_kg
            : ($before > 0 ? (float) $entry->actual_total_cost / $before : (float) $entry->material?->standard_cost_per_uom));
        if (round($quantity, 3) === round($before, 3)) {
            return $entry;
        }
        $entry->update([
            'actual_qty_kg' => $quantity,
            'actual_total_cost' => round($quantity * $unitCost, 4),
            'notes' => trim(($entry->notes ? $entry->notes.' ' : '').'Weight changed from '.Formats::number($before, 3).' kg to '.Formats::number($quantity, 3).' kg by '.$by.' on '.now()->displayDateTime().'.'),
        ]);

        return $entry;
    }

    /** Removes an entry. Returns the charge that is now empty and skipped, if it was that charge's last entry. */
    public function remove(Heat $heat, HeatMaterialConsumption $entry): ?int
    {
        $this->assertEditable($heat, $entry);
        $round = $entry->charge_round;
        $entry->delete();
        if ($round !== null && ! $heat->materialConsumptions()->where('charge_round', $round)->exists()) {
            $heat->update(['skipped_charge_rounds' => array_values(array_unique([...$heat->skippedChargeRounds(), $round]))]);

            return $round;
        }

        return null;
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
