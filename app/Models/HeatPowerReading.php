<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeatPowerReading extends Model
{
    use HasUuids;

    protected $fillable = [
        'heat_id',
        'reading_number',
        'recorded_at',
        'actual_power_kw',
        'cumulative_energy_kwh',
        'source',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reading_number' => 'integer',
            'recorded_at' => 'datetime',
            'actual_power_kw' => 'decimal:3',
            'cumulative_energy_kwh' => 'decimal:3',
        ];
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }
}
