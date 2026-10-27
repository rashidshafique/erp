<?php

namespace App\Domains\Sales\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product's price on a list, per piece or per kg, from a quantity (pieces or kg, in the price's own basis) upwards. */
class PriceListItem extends Model
{
    use HasUuids;

    public const BASES = ['PIECE' => 'per piece', 'KG' => 'per kg'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['min_quantity' => 'float', 'price' => 'float'];
    }

    public function priceList(): BelongsTo { return $this->belongsTo(PriceList::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
