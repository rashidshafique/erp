<?php

namespace App\Models;

use App\Services\Codes\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A version of a pattern's cast-relevant geometry and layout: geometry, draft, allowances, shrinkage, gating built into the
 * pattern, parting line, cavity count and layout, core prints. Repairs, repainting and re-mounting are not revisions; they go
 * in the maintenance log. A pattern has exactly one current revision; older ones are superseded and kept.
 */
class PatternRevision extends Model
{
    use HasUuids;

    protected $fillable = [
        'pattern_id',
        'revision',
        'code',
        'sequence',
        'status',
        'is_current',
        'superseded_at',
        'legacy_baseline',
        'drawing_revision',
        'shrinkage_note',
        'modification_cost',
        'cost_note',
        'casting_weight_kg',
        'pour_weight_kg',
        'pieces_per_mould',
        'parting_type',
        'cavities_per_mould',
        'layers_per_mould',
        'pattern_weight_kg',
        'change_description',
        'engineering_notes',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'is_current' => 'boolean',
        'legacy_baseline' => 'boolean',
        'superseded_at' => 'datetime',
        'cavities_per_mould' => 'integer',
        'layers_per_mould' => 'integer',
        'pattern_weight_kg' => 'decimal:4',
        'modification_cost' => 'decimal:2',
        'casting_weight_kg' => 'decimal:4',
        'pour_weight_kg' => 'decimal:4',
        'pieces_per_mould' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected static function booted(): void
    {
        // Every revision gets the next number and a code made from the pattern code: P-0123-R1.
        static::creating(function (PatternRevision $revision) {
            if ($revision->sequence === null) {
                $last = static::where('pattern_id', $revision->pattern_id)->max('sequence');
                $revision->sequence = $last === null ? 0 : (int) $last + 1;
            }
            if (blank($revision->revision)) {
                $label = (string) $revision->sequence;
                for ($i = 2; static::where('pattern_id', $revision->pattern_id)->where('revision', $label)->exists(); $i++) {
                    $label = $revision->sequence.'-'.$i;
                }
                $revision->revision = $label;
            }
            if (blank($revision->code)) {
                $revision->code = static::codeFor(Pattern::withTrashed()->whereKey($revision->pattern_id)->value('code'), $revision->sequence);
            }
        });
        // A pattern's first revision is its current one, however it was created.
        static::created(function (PatternRevision $revision) {
            if (! $revision->is_current && ! static::where('pattern_id', $revision->pattern_id)->where('is_current', true)->exists()) {
                $revision->forceFill(['is_current' => true])->saveQuietly();
            }
        });
    }

    /** A free revision code for the pattern code and number, such as P-0123-R1. The "-R" is set in Settings → Codes. */
    public static function codeFor(?string $patternCode, int $sequence): string
    {
        $base = ($patternCode ?: 'PATTERN').static::codeSuffix().$sequence;
        $code = $base;
        for ($i = 2; static::where('code', $code)->exists(); $i++) {
            $code = $base.'-'.$i;
        }

        return $code;
    }

    /** What goes between the pattern code and the revision number, from Settings → Codes. */
    public static function codeSuffix(): string
    {
        return CodeGenerator::setting('revision_suffix', '-R');
    }

    /** Casting weight as a share of the poured weight, in percent, when both are recorded. */
    public function yieldPercent(): ?float
    {
        return $this->casting_weight_kg && $this->pour_weight_kg && (float) $this->pour_weight_kg > 0
            ? round(100 * (float) $this->casting_weight_kg / (float) $this->pour_weight_kg, 1)
            : null;
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /** Still the as-imported baseline, with no customer drawing revision recorded against it. */
    public function scopeBaselineWithoutDrawing(Builder $query): Builder
    {
        return $query->where('legacy_baseline', true)->where(fn ($q) => $q->whereNull('drawing_revision')->orWhere('drawing_revision', ''));
    }

    public function isSuperseded(): bool
    {
        return ! $this->is_current && $this->superseded_at !== null;
    }

    /** Short label for pickers and headings: "P-0123-R1 (current)". */
    public function label(): string
    {
        return ($this->code ?: 'Revision '.$this->revision)
            .($this->is_current ? ' (current)' : ($this->isSuperseded() ? ' (superseded)' : ''))
            .($this->legacy_baseline ? ' · baseline' : '');
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function cavityMap(): HasMany
    {
        return $this->hasMany(PatternRevisionCavity::class)->orderBy('cavity_number');
    }

    public function mouldCavities(): HasMany
    {
        return $this->hasMany(MouldCavity::class);
    }
}
