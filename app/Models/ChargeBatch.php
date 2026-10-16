<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeBatch extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'process_type',
        'status',
        'target_metal_weight_kg',
        'target_total_charge_weight_kg',
        'revision',
        'effective_from',
        'effective_to',
        'instructions',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_metal_weight_kg' => 'decimal:3',
            'target_total_charge_weight_kg' => 'decimal:3',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChargeBatchItem::class)
            ->orderBy('sequence');
    }

    public function heats(): HasMany
    {
        return $this->hasMany(Heat::class);
    }
}