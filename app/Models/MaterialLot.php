<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialLot extends Model
{
    use HasUuids;

    protected $fillable = [
        'lot_number',
        'material_id',
        'supplier_lot_number',
        'supplier_name_snapshot',
        'received_date',
        'manufacture_date',
        'expiry_date',
        'status',
        'received_qty',
        'accepted_qty',
        'unit_cost',
        'currency_code',
        'heat_or_batch_reference',
        'certificate_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'manufacture_date' => 'date',
            'expiry_date' => 'date',
            'received_qty' => 'decimal:3',
            'accepted_qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
    public function costLayers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            \App\Models\StockLotCostLayer::class,
            'material_lot_id'
        );
    }
    public function movements(): HasMany
    {
        return $this->hasMany(
            StockMovement::class,
            'material_lot_id'
        );
    }
}