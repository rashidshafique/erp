<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractReview extends Model
{
    use HasUuids;

    protected $fillable = [
        'sales_order_id',
        'review_number',
        'status',
        'verdict',
        'evaluated_at',
        'sales_signed_by',
        'sales_signed_by_user_id',
        'sales_signed_at',
        'quality_signed_by',
        'quality_signed_by_user_id',
        'quality_signed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return ['evaluated_at' => 'datetime', 'sales_signed_at' => 'datetime', 'quality_signed_at' => 'datetime'];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractReviewItem::class);
    }
}
