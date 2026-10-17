<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A change of a pattern's owner or custody: who it passed to, or where it went, with the paper behind it. */
class PatternOwnershipEvent extends Model
{
    use HasUuids;

    public const TYPES = [
        'OWNER_CHANGED' => 'Owner changed', 'CUSTODY_CHANGED' => 'Moved', 'PAID_OFF' => 'Paid off: now the customer\'s',
        'RETURNED' => 'Returned to the customer', 'SCRAPPED' => 'Scrapped', 'RECORDED' => 'Ownership recorded',
    ];

    protected $guarded = [];

    protected $casts = ['occurred_on' => 'date'];

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class)->withTrashed();
    }
}
