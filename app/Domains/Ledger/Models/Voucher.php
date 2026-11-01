<?php

namespace App\Domains\Ledger\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A voucher saved as a draft (design 19b). It keeps the number it was given and its lines, and never counts in the books;
 * posting it writes a journal with the same id and number and removes the draft.
 *
 * @property array<int, array{account_id: string, narration: ?string, debit: float|string|null, credit: float|string|null}> $lines
 */
class Voucher extends Model
{
    use HasUuids;

    protected $table = 'ledger_vouchers';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['voucher_date' => 'date', 'lines' => 'array'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function counterAccount(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'counter_account_id'); }

    /** What the lines add up to: the larger of debits and credits, with the balancing line of a cash or bank voucher. */
    public function amount(): float
    {
        $dr = collect($this->lines)->sum(fn ($l) => (float) ($l['debit'] ?? 0));
        $cr = collect($this->lines)->sum(fn ($l) => (float) ($l['credit'] ?? 0));

        return round($this->counter_account_id ? abs($dr - $cr) : max($dr, $cr), 2);
    }
}
