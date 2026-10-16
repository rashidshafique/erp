<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pattern extends Model
{
    use HasUuids, SoftDeletes, \App\Models\Concerns\GeneratesCodes;

    public string $codeKind = 'pattern';

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
}