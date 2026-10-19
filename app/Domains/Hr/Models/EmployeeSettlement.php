<?php

namespace App\Domains\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSettlement extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_working_day' => 'date', 'total_due' => 'float', 'total_recovery' => 'float', 'net' => 'float', 'calculated_at' => 'datetime', 'approved_at' => 'datetime', 'paid_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function exit(): BelongsTo
    {
        return $this->belongsTo(EmployeeExit::class, 'exit_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SettlementLine::class, 'settlement_id')->orderBy('sort');
    }
}
