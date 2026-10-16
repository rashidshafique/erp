<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrDesignation extends Model
{
    use GeneratesCodes, HasUuids;

    protected $guarded = [];

    public string $codeKind = 'hr_designation';

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(HrGrade::class, 'grade_id');
    }
}
