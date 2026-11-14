<?php

namespace App\Domains\Assets\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One part of an asset's cost: a supplier bill line, a journal, pattern-shop hours, materials, or the cost brought in when the asset was added. */
class AssetCost extends Model
{
    use HasUuids;

    public const SOURCES = ['OPENING' => 'Brought in', 'BILL_LINE' => 'Supplier bill', 'JOURNAL' => 'Journal', 'SHOP_HOURS' => 'Shop hours', 'MATERIALS' => 'Materials', 'OTHER' => 'Other'];

    protected $table = 'fixed_asset_costs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'float', 'incurred_on' => 'date'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}
