<?php

namespace App\Models;

use App\Services\Codes\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A version of a pattern's cast-relevant geometry and layout: geometry, draft, allowances, shrinkage, gating built into the
 * pattern, parting line, cavity count and layout, core prints. Repairs, repainting and re-mounting are not revisions; they go
 * in the maintenance log. A pattern has exactly one current revision; older ones are superseded and kept.
 */
class PatternRevision extends Model
{
    use HasUuids;

    /**
     * How the revision is moulded: the mould, not the tooling (a matchplate, a loose pattern or a gated pattern is a pattern type,
     * and is moulded as cope and drag). Every arrangement counts castings the same way: casting levels × the cavity layout, where
     * a level is a joint between a bottom and a top flask (or between two blocks of a flaskless line).
     */
    public const ARRANGEMENTS = [
        'COPE_DRAG' => 'Cope and drag (horizontally parted, two flasks)',
        'STACK' => 'Stack mould (sections stacked on one sprue)',
        'VERTICAL' => 'Vertically parted, flaskless (DISA type)',
        'SHELL' => 'Shell mould',
        'CORE_PACKAGE' => 'Core package (stacked cores)',
        'CLUSTER' => 'Tree or cluster',
        'OTHER' => 'Other',
    ];

    /** ISO 8062-3 casting tolerance grades (DCTG) and machining allowance grades (RMAG) a drawing can call out. */
    public const TOLERANCE_GRADES = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16];

    public const PARTING_TYPES = ['HORIZONTAL', 'VERTICAL', 'SINGLE_SIDED', 'OTHER'];

    public const ALLOWANCE_GRADES = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K'];

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
        'shrinkage_percent',
        'tolerance_grade',
        'machining_allowance_grade',
        'modification_cost',
        'cost_note',
        'casting_weight_kg',
        'pour_weight_kg',
        'pieces_per_mould',
        'mould_arrangement',
        'casting_levels',
        'runner_weight_per_level_kg',
        'sprue_weight_kg',
        'poured_weight_per_mould_kg',
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
        'shrinkage_percent' => 'decimal:3',
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
        'casting_levels' => 'integer',
        'runner_weight_per_level_kg' => 'decimal:4',
        'sprue_weight_kg' => 'decimal:4',
        'poured_weight_per_mould_kg' => 'decimal:4',
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
            if (blank($revision->mould_arrangement)) {
                $type = Pattern::withTrashed()->whereKey($revision->pattern_id)->value('pattern_type');
                $revision->mould_arrangement = Pattern::TYPES[$type] ?? 'OTHER';
            }
            $revision->casting_levels ??= 1;
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

    /** Joints holding castings in one mould: 1 for cope and drag, the sections less one for a stack. */
    public function castingLevels(): int
    {
        return max(1, (int) ($this->casting_levels ?? 1));
    }

    public function arrangementLabel(): string
    {
        return self::ARRANGEMENTS[$this->mould_arrangement] ?? 'Not set';
    }

    public function isSuperseded(): bool
    {
        return ! $this->is_current && $this->superseded_at !== null;
    }

    /** The revision as shown everywhere: its code ("P-0123-R1"), else its revision label. */
    public function shortCode(): string
    {
        return $this->code ?: (string) $this->revision;
    }

    /** Short label for pickers and headings: "P-0123-R1 (current)". */
    public function label(): string
    {
        return ($this->code ?: 'Revision '.$this->revision)
            .($this->is_current ? ' (current)' : ($this->isSuperseded() ? ' (superseded)' : ''))
            .($this->legacy_baseline ? ' · baseline' : '');
    }

    /** The flask sizes this revision fits; the one it was designed for is marked primary and listed first. */
    public function flaskSizes(): BelongsToMany
    {
        return $this->belongsToMany(FlaskSize::class)->withPivot('is_primary')->orderByPivot('is_primary', 'desc')->orderBy('flask_sizes.sort_order')->orderBy('flask_sizes.name');
    }

    /** Moulds of a vertically parted flaskless arrangement never use flasks. */
    public function isFlaskless(): bool
    {
        return $this->mould_arrangement === 'VERTICAL';
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class)->withTrashed();   // archived patterns still show on old records
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
