<?php

namespace App\Domains\Ledger\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A debit or a credit on one account, optionally naming the customer or supplier it concerns. */
class JournalLine extends Model
{
    use HasUuids;

    protected $table = 'ledger_journal_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['debit' => 'float', 'credit' => 'float'];
    }

    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
    public function account(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'account_id'); }
}
