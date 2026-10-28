<?php

namespace App\Domains\Enquiry\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One department's signed verdict on whether a part can be made. */
class EnquiryItemReview extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EnquiryItem::class, 'enquiry_item_id');
    }
}
