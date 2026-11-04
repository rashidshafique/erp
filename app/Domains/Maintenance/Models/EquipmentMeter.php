<?php

namespace App\Domains\Maintenance\Models;

use App\Models\Equipment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A running counter on a machine: hours, cycles, kWh, tonnes. Maintenance plans can fall due on it. */
class EquipmentMeter extends Model
{
    use HasUuids;

    public const UNITS = [
        'HOURS' => 'Hours', 'CYCLES' => 'Cycles', 'MOULDS' => 'Moulds',
        'KWH' => 'kWh', 'TONNES' => 'Tonnes', 'PIECES' => 'Pieces',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'current_reading' => 'decimal:3',
            'is_cumulative' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function readings(): HasMany
    {
        return $this->hasMany(EquipmentMeterReading::class)->orderByDesc('recorded_at');
    }

    /** The unit as a plan or meter bar names it: h, cycles (heats on a furnace), moulds, kWh, t or pieces. */
    public function shortUnit(): string
    {
        return match ($this->unit) {
            'HOURS' => 'h',
            'CYCLES' => $this->equipment?->equipment_type === 'FURNACE' ? 'heats' : 'cycles',
            'MOULDS' => 'moulds',
            'KWH' => 'kWh',
            'TONNES' => 't',
            'PIECES' => 'pieces',
            default => strtolower($this->unit),
        };
    }

    /** Records a reading and moves the meter's current value. */
    public function recordReading(float $value, ?string $source = null, ?string $recordedBy = null, ?string $notes = null, ?CarbonInterface $at = null): EquipmentMeterReading
    {
        $reading = $this->readings()->create([
            'reading_value' => $value,
            'recorded_at' => $at ?? now(),
            'reading_source' => $source ?? 'MANUAL',
            'recorded_by' => $recordedBy,
            'notes' => $notes,
        ]);

        $this->update(['current_reading' => $value]);

        return $reading;
    }
}
