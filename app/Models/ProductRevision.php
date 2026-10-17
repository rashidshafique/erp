<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRevision extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'revision',
        'status',
        'net_weight_kg',
        'engineering_notes',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'net_weight_kg' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();   // archived products still show on old records
    }

    public function patternCavities(): HasMany
    {
        return $this->hasMany(PatternRevisionCavity::class);
    }

    public function manufacturingMethods(): HasMany
    {
        return $this->hasMany(ManufacturingMethod::class);
    }
}
