<?php

namespace App\Domains\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptAllocation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public function receipt(): BelongsTo { return $this->belongsTo(Receipt::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
