<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessRoute extends Model
{
    use HasUuids;

    /** What the route is for. A product can have its own route in place of the foundry's one default route. */
    public const APPLIES_TO = ['DEFAULT' => 'Every casting (the foundry default)', 'PRODUCT' => 'A product'];

    protected $fillable = [
        'manufacturing_method_id',
        'applies_to',
        'product_id',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class)->orderBy('sequence');
    }
}