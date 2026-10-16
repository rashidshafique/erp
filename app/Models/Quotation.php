<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use GeneratesCodes, HasUuids;

    public string $codeKind = 'quotation';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['valid_until' => 'date', 'issued_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('line_no');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    /** One-off tooling price to invoice up front (zero when it is spread over the castings or not charged). */
    public function upfrontToolingPrice(): float
    {
        if ($this->tooling_mode !== 'UPFRONT') {
            return 0.0;
        }
        $markup = (float) \App\Services\Quotation\Rates::get()['tooling_markup_pct'];

        return round($this->lines->sum('tooling_cost') * (1 + $markup / 100), 2);
    }
}
