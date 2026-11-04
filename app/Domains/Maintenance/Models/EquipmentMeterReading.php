<?php

namespace App\Domains\Maintenance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One reading taken from a meter, kept as the history behind its current value. */
class EquipmentMeterReading extends Model
{
    use HasUuids;

    public const SOURCES = ['MANUAL' => 'Manual', 'IMPORT' => 'Imported', 'RUN' => 'From a production run'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reading_value' => 'decimal:3',
            'recorded_at' => 'datetime',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(EquipmentMeter::class, 'equipment_meter_id');
    }
}
