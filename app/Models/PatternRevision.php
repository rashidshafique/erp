<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatternRevision extends Model
{
    use HasUuids;

    protected $fillable = [
        'pattern_id',
        'revision',
        'status',
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
        'cavities_per_mould' => 'integer',
        'layers_per_mould' => 'integer',
        'pattern_weight_kg' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

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