<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Heat extends Model
{
    use HasUuids;

    protected $fillable = [
        'heat_number',
        'furnace_id',
        'charge_batch_id',
        'iron_grade_id',
        'process_type',
        'status',
        'planned_start_at',
        'actual_start_at',
        'melt_completed_at',
        'tapped_at',
        'closed_at',
        'target_metal_weight_kg',
        'actual_tapped_weight_kg',
        'target_temperature_c',
        'actual_tap_temperature_c',
        'operator_name',
        'notes',
        'operating_power_limit_kw',
        'blast_on_at',
        'first_tap_at',
        'dropped_at',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'melt_completed_at' => 'datetime',
            'tapped_at' => 'datetime',
            'closed_at' => 'datetime',
            'blast_on_at' => 'datetime',
            'first_tap_at' => 'datetime',
            'dropped_at' => 'datetime',
            'target_metal_weight_kg' => 'decimal:3',
            'actual_tapped_weight_kg' => 'decimal:3',
            'target_temperature_c' => 'decimal:2',
            'actual_tap_temperature_c' => 'decimal:2',
            'operating_power_limit_kw' => 'decimal:3',
        ];
    }

    /** A cupola melts continuously and taps as it goes, so it is run on a timeline rather than as one batch. */
    public function isCupola(): bool
    {
        return ($this->furnace?->furnace_type ?? $this->process_type) === 'CUPOLA';
    }

    public function furnace(): BelongsTo
    {
        return $this->belongsTo(Furnace::class);
    }

    public function ironGrade(): BelongsTo
    {
        return $this->belongsTo(IronGrade::class);
    }

    public function thermalTests(): HasMany
    {
        return $this->hasMany(ThermalAnalysisTest::class)->orderByDesc('taken_at');
    }

    public function chemistryReadings(): HasMany
    {
        return $this->hasMany(HeatChemistryReading::class)->orderBy('reading_number');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(HeatTreatment::class)->orderBy('treated_at');
    }

    public function pours(): HasMany
    {
        return $this->hasMany(Pour::class);
    }

    public function powerReadings(): HasMany
    {
        return $this->hasMany(HeatPowerReading::class)
            ->orderBy('recorded_at')
            ->orderBy('reading_number');
    }

    public function processReadings(): HasMany
    {
        return $this->hasMany(HeatProcessReading::class)
            ->orderBy('recorded_at')
            ->orderBy('reading_number');
    }

    public function chargeBatch(): BelongsTo
    {
        return $this->belongsTo(ChargeBatch::class);
    }

    public function materialConsumptions(): HasMany
    {
        return $this->hasMany(HeatMaterialConsumption::class)
            ->orderBy('sequence');
    }

    public function chargePosting(): HasOne
    {
        return $this->hasOne(HeatChargePosting::class);
    }
}
