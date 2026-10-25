<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoreBox extends Model
{
    use HasUuids, SoftDeletes, \App\Models\Concerns\GeneratesCodes;

    public string $codeKind = 'core_box';

    public const ORIGINS = [
        'NEW' => 'New box',
        'MODIFIED_BOX' => 'Modified from another core box',
        'MODIFIED_PATTERN' => 'Modified from a pattern',
    ];

    protected $fillable = [
        'mark_code',
        'origin',
        'modified_from_core_box_id',
        'modified_from_pattern_id',
        'core_definition_id',
        'code',
        'name',
        'revision',
        'status',
        'material',
        'cavities',
        'nominal_core_weight_kg',
        'description',
        'notes',
        'commissioned_on',
    ];

    protected $casts = [
        'cavities' => 'integer',
        'nominal_core_weight_kg' => 'decimal:4',
        'commissioned_on' => 'date',
    ];

    public function coreDefinition(): BelongsTo
    {
        return $this->belongsTo(CoreDefinition::class);
    }

    protected static function booted(): void
    {
        // A box made without a cavity map (an import, say) makes its core in every cavity.
        static::created(function (CoreBox $box) {
            if ($box->core_definition_id && ! $box->cavityMap()->exists()) {
                $box->saveCavityMap(array_fill(1, max(1, (int) $box->cavities), $box->core_definition_id));
            }
        });
    }

    /** The cores this box makes, one row per cavity. */
    public function cavityMap(): HasMany
    {
        return $this->hasMany(CoreBoxCavity::class)->orderBy('cavity_number');
    }

    public function modifiedFromBox(): BelongsTo
    {
        return $this->belongsTo(self::class, 'modified_from_core_box_id')->withTrashed();
    }

    public function modifiedFromPattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class, 'modified_from_pattern_id')->withTrashed();
    }

    /**
     * Sets the core made in each cavity and keeps the box's main core (the one in its lowest filled cavity) in step.
     *
     * @param  array<int, string|null>  $coreByCavity  cavity number => core id, or null for a cavity not in use
     */
    public function saveCavityMap(array $coreByCavity): void
    {
        $this->cavityMap()->delete();
        foreach ($coreByCavity as $number => $coreId) {
            if (filled($coreId)) {
                $this->cavityMap()->create(['cavity_number' => (int) $number, 'core_definition_id' => $coreId]);
            }
        }
        if ($first = $this->cavityMap()->first()) {
            $this->update(['core_definition_id' => $first->core_definition_id]);
        }
    }

    /** @return \Illuminate\Support\Collection<int, CoreDefinition> the distinct cores this box makes */
    public function coresMade()
    {
        return $this->cavityMap()->with('coreDefinition')->get()->pluck('coreDefinition')->unique('id')->values();
    }
}
