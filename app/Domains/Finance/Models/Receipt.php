<?php

namespace App\Domains\Finance\Models;

use App\Domains\Sales\Models\SalesOrder;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money received from a customer, spread over the invoices it pays; any part not allocated stays as the customer's credit. An advance
 * can name the sales order it is for.
 */
class Receipt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'amount' => 'float'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function allocations(): HasMany { return $this->hasMany(ReceiptAllocation::class); }
    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class); }

    public function unallocated(): float
    {
        return round($this->amount - (float) $this->allocations()->sum('amount'), 2);
    }
}
