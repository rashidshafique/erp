<?php

namespace App\Domains\Assets\Models;

use App\Domains\Ledger\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A group of assets sharing a cost account, an accumulated depreciation account, an expense account and a default way to depreciate. */
class AssetClass extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['life_years' => 'float', 'rate_percent' => 'float', 'sort_order' => 'integer'];
    }

    public function costAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'cost_account_id');
    }

    public function depreciationAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'depreciation_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'expense_account_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class);
    }
}
