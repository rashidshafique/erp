<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Furnace extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'furnace_type',
        'status',
        'rated_capacity_kg',
        'nominal_melting_rate_kg_per_hour',
        'rated_power_kw',
        'description',
        'notes',
        'equipment_id',
        'max_operating_power_kw',
    ];

    protected function casts(): array
    {
        return [
            'rated_capacity_kg' => 'decimal:3',
            'nominal_melting_rate_kg_per_hour' => 'decimal:3',
            'rated_power_kw' => 'decimal:3',
            'max_operating_power_kw' => 'decimal:3',
        ];
    }

    public function heats(): HasMany
    {
        return $this->hasMany(Heat::class);
    }

    /**
     * What the furnace is doing now: under maintenance or out of service by its own status, else melting a running heat or idle.
     *
     * @return array{label: string, tone: string, heat: ?Heat}
     */
    public function currentState(): array
    {
        if ($this->status === 'MAINTENANCE') {
            return ['label' => 'Under maintenance', 'tone' => 'warn', 'heat' => null];
        }
        if ($this->status === 'INACTIVE') {
            return ['label' => 'Out of service', 'tone' => 'off', 'heat' => null];
        }
        $running = $this->heats()->whereIn('status', Heat::RUNNING_STATUSES)->orderByDesc('actual_start_at')->first();

        return $running ? ['label' => 'Melting', 'tone' => 'on', 'heat' => $running] : ['label' => 'Idle', 'tone' => 'idle', 'heat' => null];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
