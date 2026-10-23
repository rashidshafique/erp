<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessRoute extends Model
{
    use HasUuids;

    /** What the route is for. A product's route comes from its manufacturing method; the foundry has one default route. */
    public const APPLIES_TO = ['DEFAULT' => 'Every casting (the foundry default)', 'PRODUCT' => 'A product'];

    protected $fillable = [
        'manufacturing_method_id',
        'applies_to',
        'code',
        'name',
        'revision',
        'status',
        'is_primary',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function manufacturingMethod(): BelongsTo
    {
        return $this->belongsTo(ManufacturingMethod::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class)->orderBy('sequence');
    }
}