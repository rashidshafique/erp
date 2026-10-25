<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoreDefinition extends Model
{
    use HasUuids, SoftDeletes, \App\Models\Concerns\GeneratesCodes;

    public string $codeKind = 'core_definition';

    public const TYPES = [
        'STANDARD' => 'Sand core',
        'CERAMIC' => 'Ceramic core',
        'OTHER' => 'Other',
    ];

    protected $fillable = [
        'mark_code',
        'code',
        'name',
        'core_type',
        'status',
        'nominal_weight_kg',
        'process_type',
        'description',
        'engineering_notes',
    ];

    protected $casts = [
        'nominal_weight_kg' => 'decimal:4',
    ];

    public function coreBoxes(): HasMany
    {
        return $this->hasMany(CoreBox::class);
    }

    /** The box cavities that make this core. */
    public function cavities(): HasMany
    {
        return $this->hasMany(CoreBoxCavity::class);
    }

    /** The product revisions that need this core. */
    public function requirements(): HasMany
    {
        return $this->hasMany(CoreRequirement::class);
    }

    /** Whether an active core box makes this core. */
    public function hasActiveBox(): bool
    {
        return $this->cavities()->whereHas('coreBox', fn ($q) => $q->where('status', 'ACTIVE'))->exists();
    }
}
