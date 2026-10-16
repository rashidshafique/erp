<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlannedHeat extends Model
{
    use HasUuids;

    protected $fillable = [
        'plan_number',
        'iron_grade_id',
        'furnace_id',
        'planned_date',
        'sequence',
        'planned_metal_kg',
        'status',
        'heat_id',
        'changeover_required',
        'late_risk',
        'flags',
        'notes',
    ];

    protected function casts(): array
    {
        return ['planned_date' => 'date', 'planned_metal_kg' => 'decimal:3', 'changeover_required' => 'boolean', 'late_risk' => 'boolean'];
    }

    public function ironGrade(): BelongsTo
    {
        return $this->belongsTo(IronGrade::class);
    }

    public function furnace(): BelongsTo
    {
        return $this->belongsTo(Furnace::class);
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PlannedHeatOrder::class);
    }
}
