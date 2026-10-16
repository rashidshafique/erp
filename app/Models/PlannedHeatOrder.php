<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlannedHeatOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'planned_heat_id',
        'production_order_id',
        'metal_kg',
    ];

    protected function casts(): array
    {
        return ['metal_kg' => 'decimal:3'];
    }

    public function plannedHeat(): BelongsTo
    {
        return $this->belongsTo(PlannedHeat::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }
}
