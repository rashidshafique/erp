<?php

namespace App\Domains\Finance\Models;

use App\Models\MaterialLot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A lot's unit cost moved by a supplier bill (reason BILLED) or back to the order price by a void (reason VOIDED). */
class LotCostAdjustment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['from_unit_cost' => 'float', 'to_unit_cost' => 'float', 'quantity_on_hand' => 'float', 'quantity_used' => 'float', 'variance_on_used' => 'float'];
    }

    public function lot(): BelongsTo { return $this->belongsTo(MaterialLot::class, 'material_lot_id'); }
    public function bill(): BelongsTo { return $this->belongsTo(SupplierBill::class, 'supplier_bill_id'); }
}
