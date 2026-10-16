<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IronGradeAlias extends Model
{
    use HasUuids;

    protected $fillable = [
        'iron_grade_id',
        'standard',
        'designation',
        'is_exact',
        'normalized',
    ];

    protected function casts(): array
    {
        return ['is_exact' => 'boolean'];
    }

    public function ironGrade(): BelongsTo
    {
        return $this->belongsTo(IronGrade::class);
    }
}
