<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeasurementSet extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'findings' => 'array'];
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class)->orderBy('sort_order');
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }

    /** The machine or instrument the results were measured with. */
    public function gauge(): BelongsTo
    {
        return $this->belongsTo(Gauge::class);
    }
}
