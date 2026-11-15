<?php

namespace App\Domains\Assets\Models;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Ledger\Models\Journal;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An asset sold, sold as scrap or written off (design 28h): the price, the depreciation charged to that day, and the cost and
 * accumulated depreciation it took off the books, which the roll-forward (28i) shows as disposals.
 */
class AssetDisposal extends Model
{
    use HasUuids;

    /** @var array<string, array{0: string, 1: string}> kind => [label, the asset's status after] */
    public const KINDS = ['SELL' => ['Sell', 'SOLD'], 'SCRAP' => ['Sell as scrap', 'SCRAPPED'], 'WRITE_OFF' => ['Write off', 'WRITTEN_OFF']];

    protected $table = 'fixed_asset_disposals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'disposed_on' => 'date', 'price' => 'float', 'weight_kg' => 'float', 'charge' => 'float', 'cost_removed' => 'float',
            'depreciation_cleared' => 'float', 'book_value' => 'float', 'gain' => 'float',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
