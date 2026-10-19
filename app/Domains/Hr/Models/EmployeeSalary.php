<?php

namespace App\Domains\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSalary extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'basic' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EmployeeSalaryLine::class, 'salary_id');
    }

    /** Basic plus every allowance, before any absence cut. */
    public function gross(): float
    {
        return (float) $this->basic + (float) $this->lines->sum(fn ($l) => $l->component?->kind === 'DEDUCTION' ? -$l->amount : $l->amount);
    }
}
