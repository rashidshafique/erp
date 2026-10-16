<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class LeaveRequest extends Model
{
    use GeneratesCodes, HasUuids;

    protected $guarded = [];

    public string $codeKind = 'leave_request';

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'half_day' => 'boolean', 'days' => 'float', 'decided_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
