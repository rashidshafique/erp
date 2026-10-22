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

    /** Component roles that are not metal: they burn or flux, and take no part in how fast metal is charged. */
    public const NON_METAL_ROLES = ['COKE', 'FLUX', 'LIMESTONE', 'FUEL'];

    /**
     * Metal one charge of the recipe puts in: the weight set on the recipe, else the weight of its charge, return and alloy
     * materials (everything but coke and flux) in one round. Zero when neither is known.
     */
    public function metalPerChargeKg(): float
    {
        if ((float) $this->target_metal_weight_kg > 0) {
            return (float) $this->target_metal_weight_kg;
        }

        return (float) $this->items->where('charge_stage', 'ROUND')->reject(fn ($i) => in_array(strtoupper((string) $i->component_role), self::NON_METAL_ROLES, true))
            ->sum(fn ($i) => (float) $i->perChargeKg());
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