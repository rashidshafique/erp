<?php

namespace App\Domains\Hr\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAdvance extends Model
{
    use GeneratesCodes, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'float', 'installment_amount' => 'float', 'first_recovery_month' => 'date', 'disbursed_on' => 'date', 'decided_at' => 'datetime', 'paused' => 'boolean'];
    }

    public string $codeKind = 'hr_advance';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(AdvanceLedgerEntry::class, 'advance_id')->orderBy('entry_date')->orderBy('created_at');
    }

    /** What is still owed. */
    public function balance(): float
    {
        return round((float) AdvanceLedgerEntry::where('advance_id', $this->id)->sum('amount'), 2);
    }
}
