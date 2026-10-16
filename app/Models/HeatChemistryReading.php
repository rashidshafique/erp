<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeatChemistryReading extends Model
{
    use HasUuids;

    protected $fillable = [
        'heat_id',
        'reading_number',
        'sample_stage',
        'test_method',
        'carbon_percent',
        'silicon_percent',
        'manganese_percent',
        'sulphur_percent',
        'phosphorus_percent',
        'chromium_percent',
        'copper_percent',
        'molybdenum_percent',
        'magnesium_percent',
        'carbon_equivalent',
        'is_accepted',
        'is_verified',
        'instrument_reference',
        'sample_reference',
        'tested_by',
        'tested_at',
        'notes',
    ];

    protected function casts(): array
    {
        return ['is_accepted' => 'boolean', 'is_verified' => 'boolean', 'tested_at' => 'datetime'];
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }
}
