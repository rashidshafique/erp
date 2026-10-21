<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargeBatchItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'charge_batch_id',
        'material_id',
        'sequence',
        'component_role',
        'charge_stage',
        'target_qty_kg',
        'target_percent',
        'minimum_qty_kg',
        'maximum_qty_kg',
        'is_required',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_qty_kg' => 'decimal:3',
            'target_percent' => 'decimal:4',
            'minimum_qty_kg' => 'decimal:3',
            'maximum_qty_kg' => 'decimal:3',
            'is_required' => 'boolean',
        ];
    }

    public function chargeBatch(): BelongsTo
    {
        return $this->belongsTo(ChargeBatch::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}