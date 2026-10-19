<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pour extends Model
{
    use HasUuids;

    protected $fillable = [
        'pour_number',
        'heat_id',
        'status',
        'planned_at',
        'started_at',
        'completed_at',
        'pour_temperature_c',
        'total_poured_weight_kg',
        'operator_name',
        'ladle_reference',
        'notes',
        'gate_mode',
        'gate_note',
        'is_first_iron',
        'containment_note',
        'gauge_id',
    ];

    protected function casts(): array
    {
        return [
            'planned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'pour_temperature_c' => 'decimal:2',
            'total_poured_weight_kg' => 'decimal:3',
            'is_first_iron' => 'boolean',
        ];
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }

    /** The pyrometer the pour temperature was read with. */
    public function gauge(): BelongsTo
    {
        return $this->belongsTo(Gauge::class);
    }

    public function mouldAssemblies(): HasMany
    {
        return $this->hasMany(PourMouldAssembly::class);
    }
}
