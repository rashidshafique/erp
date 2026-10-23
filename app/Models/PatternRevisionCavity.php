<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatternRevisionCavity extends Model
{
    use HasUuids;

    public const ROLE_WHOLE = 'WHOLE';

    public const ROLE_PART = 'PART';

    protected $fillable = [
        'pattern_revision_id',
        'cavity_number',
        'product_revision_id',
        'cavity_role',
        'component_label',
        'pieces_per_product',
        'casting_weight_kg',
        'poured_weight_kg',
        'notes',
        'is_closed',
        'cavity_mark',
        'copied_from_revision_id',
        'needs_review',
    ];

    protected $casts = [
        'cavity_number' => 'integer',
        'pieces_per_product' => 'integer',
        'casting_weight_kg' => 'decimal:4',
        'poured_weight_kg' => 'decimal:4',
        'needs_review' => 'boolean',
        'is_closed' => 'boolean',
    ];

    public function patternRevision(): BelongsTo
    {
        return $this->belongsTo(PatternRevision::class);
    }

    /** The revision this cavity was copied from when a new revision took over its layout. */
    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(PatternRevision::class, 'copied_from_revision_id');
    }

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }

    /** Casting weight as a share of poured weight (gating and risers excluded), in percent. */
    public function yieldPercent(): ?float
    {
        if (! $this->casting_weight_kg || ! $this->poured_weight_kg || (float) $this->poured_weight_kg <= 0) {
            return null;
        }

        return round((float) $this->casting_weight_kg / (float) $this->poured_weight_kg * 100, 1);
    }

    public function label(): string
    {
        return $this->cavity_role === self::ROLE_PART
            ? 'Part: '.$this->component_label
            : 'Whole casting';
    }
}
