<?php

namespace App\Domains\Assets\Models;

use App\Domains\Ledger\Models\Journal;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A month's depreciation, kept once it is posted (design 28f). Before that the month is worked out fresh each time it is opened.
 * A reversed run stays, beside its reversing journal; running the month again posts a new run.
 */
class DepreciationRun extends Model
{
    use HasUuids;

    public const POSTED = 'POSTED';

    public const REVERSED = 'REVERSED';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['period' => 'date', 'total' => 'float', 'posted_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DepreciationRunLine::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function reversalJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'reversal_journal_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_id');
    }

    /** The last day of the run's month: the date its journal and any reversal carry. */
    public function periodEnd(): Carbon
    {
        return $this->period->copy()->endOfMonth();
    }

    public function label(): string
    {
        return $this->period->format('F Y');
    }
}
