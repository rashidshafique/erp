<?php

namespace App\Domains\Ledger\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One bank statement matched to the books: it opens on the last statement's balance, and the journal lines ticked as cleared must
 * bring that to the statement's closing balance before it can be finished.
 */
class Reconciliation extends Model
{
    use HasUuids;

    protected $table = 'ledger_reconciliations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['statement_date' => 'date', 'opening_balance' => 'float', 'statement_balance' => 'float', 'completed_at' => 'datetime'];
    }

    public function bankAccount(): BelongsTo { return $this->belongsTo(BankAccount::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function lines(): BelongsToMany { return $this->belongsToMany(JournalLine::class, 'ledger_reconciliation_lines', 'reconciliation_id', 'journal_line_id'); }

    public function isDone(): bool
    {
        return $this->status === 'DONE';
    }
}
