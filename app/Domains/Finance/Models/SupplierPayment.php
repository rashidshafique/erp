<?php

namespace App\Domains\Finance\Models;

use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money paid to a supplier, spread over the bills it settles; any part not allocated stays as an advance with the supplier. An advance
 * can name the purchase order it is for.
 */
class SupplierPayment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'float', 'voided_at' => 'datetime'];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class)->withTrashed(); }
    public function allocations(): HasMany { return $this->hasMany(SupplierPaymentAllocation::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }

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
