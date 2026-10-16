<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeatProcessReading extends Model
{
    use HasUuids;

    protected $fillable = [
        'heat_id',
        'reading_number',
        'parameter_code',
        'numeric_value',
        'unit',
        'recorded_at',
        'source',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reading_number' => 'integer',
            'numeric_value' => 'decimal:4',
            'recorded_at' => 'datetime',
        ];
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }
}
