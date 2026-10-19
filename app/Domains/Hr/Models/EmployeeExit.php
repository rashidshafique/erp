<?php

namespace App\Domains\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeExit extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['notice_given_on' => 'date', 'last_working_day' => 'date', 'completed_at' => 'datetime', 'rehire_eligible' => 'boolean'];
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(EmployeeSettlement::class, 'exit_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
