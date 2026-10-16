<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeTask extends Model
{
    use HasUuids;

    protected $guarded = [];

    public const OWNERS = ['HR' => 'HR', 'MANAGER' => 'Line manager', 'SAFETY' => 'Safety', 'PAYROLL' => 'Payroll / accounts', 'STORES' => 'Stores', 'IT' => 'IT', 'EMPLOYEE' => 'The employee'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isOverdue(): bool
    {
        return ! $this->done_at && $this->due_on && $this->due_on->lt(now()->startOfDay());
    }
}
