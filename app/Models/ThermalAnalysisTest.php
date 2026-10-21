<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
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
        'test_started_at',
        'test_finished_at',
        'sample_point',
        'cup_batch',
        'entered_by_name',
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
        'ce_source',
        'carbon_from_tl',
        'silicon_used',
        'result',
        'findings',
        'inoculant_added_pct',
        'notes',
        'voided_at',
        'void_reason',
        'voided_by_name',
    ];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'test_started_at' => 'datetime', 'test_finished_at' => 'datetime', 'curve' => 'array', 'findings' => 'array', 'voided_at' => 'datetime'];
    }

    /** A voided test is a wrong entry kept for the record; it drops out of gates, latest-test lookups and calibration. */
    protected static function booted(): void
    {
        static::addGlobalScope('notVoided', fn (Builder $q) => $q->whereNull($q->getModel()->getTable().'.voided_at'));
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->withoutGlobalScope('notVoided')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /** Where the CE came from: the meter's own reading, or worked out from TL. */
    public function ceSourceLabel(): ?string
    {
        return match ($this->ce_source) {
            'METER' => 'from the meter',
            'CALCULATED' => 'calculated from TL',
            default => null,
        };
    }

    public const SAMPLE_POINTS = ['FURNACE' => 'Furnace', 'TREATMENT_LADLE' => 'Treatment ladle', 'POURING_LADLE' => 'Pouring ladle'];

    /** How long the cup took from start to finish, in seconds. */
    public function testDurationSeconds(): ?int
    {
        return $this->test_started_at && $this->test_finished_at ? $this->test_started_at->diffInSeconds($this->test_finished_at) : null;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
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
