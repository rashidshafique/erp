<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Moulds of one pattern counted on the floor before a pour, against what the records said was waiting. */
class MouldCount extends Model
{
    use HasUuids;

    /** Why counted moulds fell short of the records. */
    public const REASONS = [
        'BROKEN' => 'Broken or collapsed',
        'CRUSHED' => 'Crushed or shifted',
        'DRIED' => 'Dried out, stood too long',
        'NOT_MADE' => 'Never made (entered by mistake)',
        'OTHER' => 'Other',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['counted_at' => 'datetime'];
    }

    public function patternRevision(): BelongsTo
    {
        return $this->belongsTo(PatternRevision::class);
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }
}
