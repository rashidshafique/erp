<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLotCostLayer extends Model
{
    use HasUuids;

    protected $fillable = [
        'material_lot_id',
        'stock_movement_id',
        'original_qty',
        'remaining_qty',
        'unit_cost',
        'currency_code',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'original_qty' => 'decimal:3',
            'remaining_qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'received_at' => 'datetime',
        ];
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}