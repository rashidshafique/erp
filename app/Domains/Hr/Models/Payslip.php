<?php

namespace App\Domains\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['flags' => 'array', 'paid_on' => 'date', 'net' => 'float', 'gross' => 'float', 'income_tax' => 'float', 'total_earnings' => 'float', 'total_deductions' => 'float', 'basic' => 'float', 'period_days' => 'float', 'payable_days' => 'float', 'unpaid_days' => 'float', 'absent_days' => 'float', 'unpaid_leave_days' => 'float', 'eobi_employee' => 'float', 'eobi_employer' => 'float', 'ss_employer' => 'float', 'advance_recovery' => 'float', 'taxable_income' => 'float'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->orderBy('sort');
    }
}
