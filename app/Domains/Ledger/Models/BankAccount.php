<?php

namespace App\Domains\Ledger\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A bank's details on the ledger account its money is posted to. Its statements are reconciled against that account's lines. */
class BankAccount extends Model
{
    use HasUuids;

    protected $table = 'ledger_bank_accounts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function account(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'account_id'); }
    public function reconciliations(): HasMany { return $this->hasMany(Reconciliation::class)->orderByDesc('statement_date'); }
    public function lastReconciled(): HasOne { return $this->hasOne(Reconciliation::class)->where('status', 'DONE')->orderByDesc('statement_date'); }
    public function draft(): HasOne { return $this->hasOne(Reconciliation::class)->where('status', 'DRAFT'); }

    public function label(): string
    {
        return $this->bank_name.($this->account_number ? ' · '.$this->account_number : '');
    }

    /** Lines on the ledger account not ticked on any statement yet. */
    public function unclearedLines(): Builder
    {
        return JournalLine::where('account_id', $this->account_id)
            ->whereNotIn('id', fn ($q) => $q->select('journal_line_id')->from('ledger_reconciliation_lines'));
    }
}
