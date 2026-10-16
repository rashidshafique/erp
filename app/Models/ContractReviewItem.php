<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractReviewItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'contract_review_id',
        'sales_order_line_id',
        'check_code',
        'severity',
        'message',
        'acknowledgement',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ContractReview::class, 'contract_review_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }
}
