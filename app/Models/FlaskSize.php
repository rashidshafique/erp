<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A family of interchangeable flasks: the same inside size and pin centres, so any drag of it closes with any cope of it. */
class FlaskSize extends Model
{
    use Concerns\IsNamedListEntry, HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'inside_length_mm' => 'decimal:2', 'inside_width_mm' => 'decimal:2', 'cope_depth_mm' => 'decimal:2',
        'drag_depth_mm' => 'decimal:2', 'pin_centres_mm' => 'decimal:2', 'sort_order' => 'integer',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function flasks(): HasMany
    {
        return $this->hasMany(Flask::class);
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(MouldingStation::class);
    }

    /** "600 × 500 · 150/150" from the dimensions that are recorded. */
    public function dimensions(): string
    {
        $mm = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $inside = $this->inside_length_mm || $this->inside_width_mm ? $mm($this->inside_length_mm).' × '.$mm($this->inside_width_mm) : null;
        $depths = $this->cope_depth_mm || $this->drag_depth_mm ? ($mm($this->cope_depth_mm) ?? '?').'/'.($mm($this->drag_depth_mm) ?? '?') : null;

        return implode(' · ', array_filter([$inside, $depths]));
    }
}
