<?php

namespace App\Domains\Development\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a development's log: it was opened, moved to a stage, cancelled, or someone wrote a note. */
class PatternDevelopmentEvent extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function development(): BelongsTo
    {
        return $this->belongsTo(PatternDevelopment::class, 'pattern_development_id');
    }
}
