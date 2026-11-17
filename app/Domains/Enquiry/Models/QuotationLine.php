<?php

namespace App\Domains\Enquiry\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationLine extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['breakdown' => 'array', 'heat_treatment' => 'boolean', 'ndt' => 'boolean'];
    }

    /** Pieces a year this revision quotes: its own figure, else the enquiry item's. */
    public function annualQty(): ?int
    {
        $qty = $this->annual_qty ?: $this->item?->annual_qty;

        return $qty ? (int) $qty : null;
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EnquiryItem::class, 'enquiry_item_id');
    }
}
