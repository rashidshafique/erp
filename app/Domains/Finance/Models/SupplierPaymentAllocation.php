<?php

namespace App\Domains\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPaymentAllocation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id'); }
    public function bill(): BelongsTo { return $this->belongsTo(SupplierBill::class, 'supplier_bill_id'); }
}
