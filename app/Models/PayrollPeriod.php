<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['month' => 'date', 'starts_on' => 'date', 'ends_on' => 'date', 'summary' => 'array', 'calculated_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'period_id');
    }
}
