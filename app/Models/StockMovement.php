<?php

namespace App\Models;

use App\Services\Stock\StockItemTypes;
use App\Services\Stock\Stockable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasUuids;

    protected $fillable = [
        'item_type',
        'item_id',
        'movement_number',
        'movement_type',
        'material_id',
        'material_lot_id',
        'from_location_id',
        'to_location_id',
        'receipt_lot_id',
        'quantity',
        'uom',
        'unit_cost',
        'total_cost',
        'source_type',
        'source_id',
        'occurred_at',
        'recorded_by',
        'reason',
    ];

    /** Movements written with only a material and lot, as older code does, are given the matching item columns. */
    protected static function booted(): void
    {
        static::creating(function (self $movement) {
            if ($movement->item_id === null && $movement->material_id !== null) {
                $movement->item_type = 'material';
                $movement->item_id = $movement->material_id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    /** The item moved, whatever its type. */
    public function item(): ?Stockable
    {
        return app(StockItemTypes::class)->find($this->item_type, $this->item_id);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class);
    }
}