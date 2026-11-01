<?php

namespace App\Domains\Ledger\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A balance typed at go-live (design 19f), waiting to be locked into the opening voucher. */
class OpeningBalance extends Model
{
    use HasUuids;

    protected $table = 'ledger_opening_balances';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['debit' => 'float', 'credit' => 'float'];
    }

    public function account(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'account_id'); }
}
