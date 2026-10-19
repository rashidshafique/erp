<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'material_type',
        'base_uom',
        'status',
        'is_stock_tracked',
        'requires_lot_tracking',
        'minimum_stock_qty',
        'reorder_qty',
        'standard_cost_per_uom',
        'description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_stock_tracked' => 'boolean',
            'requires_lot_tracking' => 'boolean',
            'minimum_stock_qty' => 'decimal:3',
            'reorder_qty' => 'decimal:3',
            'standard_cost_per_uom' => 'decimal:4',
        ];
    }

    public function lots(): HasMany
    {
        return $this->hasMany(MaterialLot::class);
    }

    public function inspectionItems(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchasing\Models\MaterialInspectionItem::class)->orderBy('sort');
    }

    public function chargeBatchItems(): HasMany
    {
        return $this->hasMany(ChargeBatchItem::class);
    }
}