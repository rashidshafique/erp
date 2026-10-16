<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FurnaceGradeCapability extends Model
{
    use HasUuids;

    protected $fillable = [
        'furnace_id',
        'iron_grade_id',
        'allowed',
        'notes',
    ];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }

    public function furnace(): BelongsTo
    {
        return $this->belongsTo(Furnace::class);
    }

    public function ironGrade(): BelongsTo
    {
        return $this->belongsTo(IronGrade::class);
    }
}
