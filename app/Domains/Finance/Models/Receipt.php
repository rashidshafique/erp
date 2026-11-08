<?php

namespace App\Domains\Finance\Models;

use App\Domains\Sales\Models\SalesOrder;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
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
        return ['received_on' => 'date', 'amount' => 'float', 'voided_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function allocations(): HasMany { return $this->hasMany(ReceiptAllocation::class); }
    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class); }

    public function cheque(): BelongsTo { return $this->belongsTo(Cheque::class); }

    /** Undone because the cheque that paid it bounced; it stays on file but no longer counts. */
    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /** What is not on a document yet; nothing once voided. */
    public function unallocated(): float
    {
        return $this->isVoided() ? 0.0 : round($this->amount - (float) $this->allocations()->sum('amount'), 2);
    }
}
