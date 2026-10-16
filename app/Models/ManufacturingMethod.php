<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManufacturingMethod extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_revision_id',
        'code',
        'name',
        'revision',
        'status',
        'preferred_melting_process',
        'description',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }

    public function processRoutes(): HasMany
    {
        return $this->hasMany(ProcessRoute::class);
    }
}