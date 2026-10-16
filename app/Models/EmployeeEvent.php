<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeEvent extends Model
{
    use HasUuids;

    protected $guarded = [];

    public const KINDS = ['JOINED' => 'Joined', 'CONFIRMED' => 'Confirmed', 'TRANSFER' => 'Transfer', 'PROMOTION' => 'Promotion', 'EXIT_STARTED' => 'Exit started', 'EXIT_CANCELLED' => 'Exit cancelled', 'EXITED' => 'Left the company', 'STATUS' => 'Status change'];

    protected function casts(): array
    {
        return ['effective_on' => 'date', 'changes' => 'array'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
