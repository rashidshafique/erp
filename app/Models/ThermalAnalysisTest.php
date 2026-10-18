<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThermalAnalysisTest extends Model
{
    use HasUuids;

    protected $fillable = [
        'heat_id',
        'iron_grade_id',
        'gauge_id',
        'sample_reference',
        'stage',
        'cup_type',
        'taken_at',
        'taken_by',
        'curve',
        'entry_mode',
        'peak_temp_c',
        'carbon_reported_pct',
        'sample_group_id',
        'tl_c',
        'te_min_c',
        'te_max_c',
        'recalescence_c',
        'solidus_c',
        'ce_from_tl',
        'carbon_from_tl',
        'silicon_used',
        'result',
        'findings',
        'inoculant_added_pct',
        'notes',
    ];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'curve' => 'array', 'findings' => 'array'];
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }

    /** The other cup(s) taken from the same sample. */
    public function siblings(): HasMany
    {
        return $this->hasMany(self::class, 'sample_group_id', 'sample_group_id')->where('id', '!=', $this->id);
    }

    public function gauge(): BelongsTo
    {
        return $this->belongsTo(Gauge::class)->withTrashed();
    }

    public function ironGrade(): BelongsTo
    {
        return $this->belongsTo(IronGrade::class);
    }
}
