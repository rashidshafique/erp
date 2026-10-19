<?php

namespace App\Domains\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid' => 'boolean', 'allow_half_day' => 'boolean', 'counts_days_off' => 'boolean', 'after_probation_only' => 'boolean', 'active' => 'boolean', 'annual_days' => 'float', 'carry_forward_max' => 'float'];
    }

    /** Types with an accrual keep a running balance; the rest are limited per request (or not at all). */
    public function tracksBalance(): bool
    {
        return $this->accrual !== 'NONE';
    }
}
