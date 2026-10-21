<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeatMaterialConsumption extends Model
{
    use HasUuids;

    protected $fillable = [
        'heat_id',
        'material_id',
        'material_lot_id',
        'sequence',
        'consumption_type',
        'source_reference',
        'planned_qty_kg',
        'actual_qty_kg',
        'transfer_cost_per_kg',
        'actual_total_cost',
        'consumed_at',
        'recorded_by',
        'notes',
        'charge_round',
    ];

    protected function casts(): array
    {
        return [
            'planned_qty_kg' => 'decimal:3',
            'actual_qty_kg' => 'decimal:3',
            'transfer_cost_per_kg' => 'decimal:4',
            'actual_total_cost' => 'decimal:4',
            'consumed_at' => 'datetime',
        ];
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class);
    }
}