<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasUuids, SoftDeletes, \App\Models\Concerns\GeneratesCodes;

    public string $codeKind = 'product';

    protected $fillable = [
        'code',
        'name',
        'product_type',
        'base_uom',
        'nominal_weight_kg',
        'description',
        'active',
    ];

    protected $casts = [
        'nominal_weight_kg' => 'decimal:4',
        'active' => 'boolean',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(ProductRevision::class);
    }
}