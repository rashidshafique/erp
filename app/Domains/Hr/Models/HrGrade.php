<?php

namespace App\Domains\Hr\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrGrade extends Model
{
    use GeneratesCodes, HasUuids;

    protected $guarded = [];

    public string $codeKind = 'hr_grade';

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function designations(): HasMany
    {
        return $this->hasMany(HrDesignation::class, 'grade_id');
    }
}
