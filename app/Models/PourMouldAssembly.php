<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PourMouldAssembly extends Model
{
    use HasUuids;

    protected $fillable = [
        'pour_id',
        'mould_assembly_id',
        'production_order_id',
        'poured_weight_kg',
        'castings_poured_qty',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'poured_weight_kg' => 'decimal:3',
            'castings_poured_qty' => 'integer',
        ];
    }

    public function pour(): BelongsTo
    {
        return $this->belongsTo(Pour::class);
    }

    public function mouldAssembly(): BelongsTo
    {
        return $this->belongsTo(MouldAssembly::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function castingLots(): HasMany
    {
        return $this->hasMany(CastingLot::class);
    }
}
