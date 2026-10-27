<?php

namespace App\Domains\Finance\Models;

use App\Domains\Purchasing\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBillLine extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_price' => 'float', 'tax_percent' => 'float', 'line_net' => 'float', 'line_tax' => 'float', 'taxes' => 'array'];
    }

    public function bill(): BelongsTo { return $this->belongsTo(SupplierBill::class, 'supplier_bill_id'); }
    public function purchaseOrderLine(): BelongsTo { return $this->belongsTo(PurchaseOrderLine::class); }
}
