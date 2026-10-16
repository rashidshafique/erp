<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessRoute extends Model
{
    use HasUuids;

    protected $fillable = [
        'manufacturing_method_id',
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