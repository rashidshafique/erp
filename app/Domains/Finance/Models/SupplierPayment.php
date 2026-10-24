<?php

namespace App\Domains\Finance\Models;

use App\Domains\Purchasing\Models\Supplier;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Money paid to a supplier, spread over the bills it settles; any part not allocated stays as an advance with the supplier. */
class SupplierPayment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'float'];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class)->withTrashed(); }
    public function allocations(): HasMany { return $this->hasMany(SupplierPaymentAllocation::class); }

    public function unallocated(): float
    {
        return round($this->amount - (float) $this->allocations()->sum('amount'), 2);
    }
}
