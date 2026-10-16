<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThermalWindow extends Model
{
    use HasUuids;

    protected $fillable = [
        'iron_grade_id',
        'cup_type',
        'tl_min_c',
        'tl_max_c',
        'ce_min',
        'ce_max',
        'te_min_floor_c',
        'recal_min_c',
        'recal_max_c',
        'solidus_min_c',
        'notes',
    ];

    public function ironGrade(): BelongsTo
    {
        return $this->belongsTo(IronGrade::class);
    }

    public function isEmpty(): bool
    {
        foreach (['tl_min_c', 'tl_max_c', 'ce_min', 'ce_max', 'te_min_floor_c', 'recal_min_c', 'recal_max_c', 'solidus_min_c'] as $f) {
            if ($this->{$f} !== null) {
                return false;
            }
        }

        return true;
    }
}
