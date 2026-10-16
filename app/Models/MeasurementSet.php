<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MeasurementSet extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'findings' => 'array'];
    }

    public function measurements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Measurement::class)->orderBy('sort_order');
    }

    public function heat(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }
}
