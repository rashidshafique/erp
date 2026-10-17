<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Pattern extends Model
{
    use GeneratesCodes, HasUuids, SoftDeletes;

    public string $codeKind = 'pattern';

    /** Pattern types, and the way each is usually moulded (PatternRevision::ARRANGEMENTS). */
    public const TYPES = [
        'MATCHPLATE' => 'MATCHPLATE', 'COPE_DRAG' => 'COPE_DRAG', 'STACK_PLATE' => 'STACK', 'VERTICAL_PLATES' => 'VERTICAL',
        'LOOSE_PATTERN' => 'LOOSE', 'SPLIT_PATTERN' => 'LOOSE', 'GATED' => 'GATED', 'OTHER' => 'OTHER',
    ];

    protected $fillable = [
        'mark_code',
        'code',
        'name',
        'pattern_type',
        'sand_system',
        'compatible_sands',
        'sand_note',
        'status',
        'material',
        'length_mm',
        'width_mm',
        'height_mm',
        'description',
        'notes',
    ];

    protected $casts = [
        'compatible_sands' => 'array',
        'length_mm' => 'decimal:2',
        'width_mm' => 'decimal:2',
        'height_mm' => 'decimal:2',
    ];

    /** Whether the pattern suits a sand system: it was made for it, is listed as working on it, or no sand has been recorded for it. */
    public function suitsSand(?string $sand): bool
    {
        if (! $sand || ! $this->sand_system) {
            return true;
        }

        return $sand === $this->sand_system || in_array($sand, $this->compatible_sands ?? [], true);
    }

    /** A plain-words warning when the pattern is not suited to the sand, else null. */
    public function sandWarning(?string $sand): ?string
    {
        if ($this->suitsSand($sand)) {
            return null;
        }

        return "Pattern {$this->code} was made for {$this->sand_system} and is not listed as suitable for {$sand}: another sand system usually needs a different taper allowance."
            .($this->sand_note ? " Note: {$this->sand_note}" : '');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PatternRevision::class);
    }

    public function currentRevision(): HasOne
    {
        return $this->hasOne(PatternRevision::class)->where('is_current', true);
    }

    /**
     * Records a new revision of the tooling geometry and makes it current. The previous current revision is superseded and
     * kept; the new one carries over its layout (parting, arrangement, cavities, sections, levels, shared metal) unless given. With $copyLayout, each
     * cavity of the previous revision is copied onto the new one and stays as it was until changed; the old cavities are left alone.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addRevision(array $attributes, bool $copyLayout = false): PatternRevision
    {
        return DB::transaction(function () use ($attributes, $copyLayout) {
            static::whereKey($this->id)->lockForUpdate()->first();
            $previous = $this->revisions()->where('is_current', true)->first();
            if ($previous) {
                // Cleared first: the database allows one current revision per pattern at any moment.
                $previous->update(['is_current' => false, 'superseded_at' => now(), 'effective_to' => $previous->effective_to ?? today()]);
            }
            $carried = $previous ? $previous->only(['parting_type', 'cavities_per_mould', 'layers_per_mould', 'pattern_weight_kg', 'mould_arrangement', 'casting_levels', 'runner_weight_per_level_kg', 'sprue_weight_kg', 'poured_weight_per_mould_kg']) : [];
            $revision = $this->revisions()->create(array_merge($carried, array_filter($attributes, fn ($v) => $v !== null), [
                'status' => $attributes['status'] ?? 'APPROVED', 'is_current' => true, 'superseded_at' => null, 'legacy_baseline' => false,
                'effective_from' => $attributes['effective_from'] ?? today(),
            ]));
            if ($previous) {
                // Process limits set on the old geometry still apply until someone changes them.
                DB::table('pattern_revisions')->where('id', $revision->id)
                    ->update(['min_cooling_minutes' => DB::table('pattern_revisions')->where('id', $previous->id)->value('min_cooling_minutes')]);
            }
            if ($previous && $copyLayout) {
                foreach ($previous->cavityMap()->get() as $cavity) {
                    $revision->cavityMap()->create($cavity->only([
                        'cavity_number', 'product_revision_id', 'cavity_role', 'component_label', 'pieces_per_product', 'casting_weight_kg', 'poured_weight_kg', 'notes',
                    ]) + ['copied_from_revision_id' => $previous->id, 'needs_review' => false]);
                }
            }
            $this->unsetRelation('revisions')->unsetRelation('currentRevision');

            return $revision->fresh();
        });
    }
}
