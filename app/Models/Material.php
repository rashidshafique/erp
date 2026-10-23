<?php

namespace App\Models;

use App\Services\Stock\Stockable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model implements Stockable
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

    public function stockItemType(): string
    {
        return 'material';
    }

    public function stockItemId(): string
    {
        return $this->id;
    }

    public function stockCode(): string
    {
        return $this->code;
    }

    public function stockName(): string
    {
        return $this->name;
    }

    public function stockUom(): string
    {
        return (string) $this->base_uom;
    }

    public function isStockTracked(): bool
    {
        return (bool) $this->is_stock_tracked;
    }

    public function isLotTracked(): bool
    {
        return (bool) $this->requires_lot_tracking;
    }

    public function standardCost(): ?float
    {
        return $this->standard_cost_per_uom === null ? null : (float) $this->standard_cost_per_uom;
    }
}
