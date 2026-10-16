<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouldCavity extends Model
{
    use HasUuids;

    protected $fillable = [
        'mould_layer_id',
        'cavity_number',
        'pattern_revision_id',
        'product_revision_id',
        'nominal_casting_weight_kg',
        'castings_per_cavity',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'cavity_number' => 'integer',
        'nominal_casting_weight_kg' => 'decimal:4',
        'castings_per_cavity' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (MouldCavity $cavity) {
            app(\App\Services\PatternCavityMap::class)->assertMouldCavityMatches($cavity);
        });
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(MouldLayer::class, 'mould_layer_id');
    }

    public function patternRevision(): BelongsTo
    {
        return $this->belongsTo(PatternRevision::class);
    }

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }
}

