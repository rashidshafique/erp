<?php

namespace App\Domains\Finance\Models;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales invoice or a credit note. It can be raised from a dispatch, from a sales order, or on its own with free lines
 * (`source_type` says which). A credit note names the invoice it reduces.
 */
class Invoice extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'issued_at' => 'datetime', 'voided_at' => 'datetime',
            'subtotal' => 'float', 'tax_total' => 'float', 'total' => 'float'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function lines(): HasMany { return $this->hasMany(InvoiceLine::class)->orderBy('line_number'); }
    public function allocations(): HasMany { return $this->hasMany(ReceiptAllocation::class); }
    public function original(): BelongsTo { return $this->belongsTo(self::class, 'original_invoice_id'); }
    public function creditNotes(): HasMany { return $this->hasMany(self::class, 'original_invoice_id')->where('kind', 'CREDIT_NOTE'); }

    public function isCreditNote(): bool
    {
        return $this->kind === 'CREDIT_NOTE';
    }

    public function paid(): float
    {
        return round((float) $this->allocations()->sum('amount'), 2);
    }

    /** What issued credit notes have taken off this invoice. */
    public function credited(): float
    {
        return round((float) $this->creditNotes()->where('status', 'ISSUED')->sum('total'), 2);
    }

    /** Still owed on an issued invoice. */
    public function balance(): float
    {
        return $this->status === 'ISSUED' && ! $this->isCreditNote() ? round($this->total - $this->paid() - $this->credited(), 2) : 0.0;
    }

    public function isOverdue(): bool
    {
        return $this->balance() > 0 && $this->due_date !== null && $this->due_date->lt(today());
    }
}
