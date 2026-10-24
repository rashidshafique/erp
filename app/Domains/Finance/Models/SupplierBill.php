<?php

namespace App\Domains\Finance\Models;

use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bill from a supplier, or a credit note received from one. It can be entered from a purchase order or on its own;
 * posting gives it our number and makes it count as owed. A credit note names the bill it reduces.
 */
class SupplierBill extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'due_date' => 'date', 'posted_at' => 'datetime', 'voided_at' => 'datetime',
            'subtotal' => 'float', 'tax_total' => 'float', 'total' => 'float'];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class)->withTrashed(); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function lines(): HasMany { return $this->hasMany(SupplierBillLine::class)->orderBy('line_number'); }
    public function allocations(): HasMany { return $this->hasMany(SupplierPaymentAllocation::class); }
    public function original(): BelongsTo { return $this->belongsTo(self::class, 'original_bill_id'); }
    public function creditNotes(): HasMany { return $this->hasMany(self::class, 'original_bill_id')->where('kind', 'CREDIT_NOTE'); }

    public function isCreditNote(): bool
    {
        return $this->kind === 'CREDIT_NOTE';
    }

    public function paid(): float
    {
        return round((float) $this->allocations()->sum('amount'), 2);
    }

    /** What posted credit notes have taken off this bill. */
    public function credited(): float
    {
        return round((float) $this->creditNotes()->where('status', 'POSTED')->sum('total'), 2);
    }

    /** Still owed on a posted bill. */
    public function balance(): float
    {
        return $this->status === 'POSTED' && ! $this->isCreditNote() ? round($this->total - $this->paid() - $this->credited(), 2) : 0.0;
    }

    public function isOverdue(): bool
    {
        return $this->balance() > 0 && $this->due_date !== null && $this->due_date->lt(today());
    }
}
