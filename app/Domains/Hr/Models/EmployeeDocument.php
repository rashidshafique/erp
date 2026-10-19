<?php

namespace App\Domains\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'expires_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function expiryState(): ?string
    {
        if (! $this->expires_on) {
            return null;
        }
        $days = (int) now()->startOfDay()->diffInDays($this->expires_on, false);

        return $days < 0 ? 'EXPIRED' : ($days <= (int) config('hr.alert_days') ? 'SOON' : 'OK');
    }
}
