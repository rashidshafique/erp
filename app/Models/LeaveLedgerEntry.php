<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class LeaveLedgerEntry extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $table = 'leave_ledger';

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'days' => 'float'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
