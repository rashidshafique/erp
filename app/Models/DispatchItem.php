<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchItem extends Model
{
    use HasUuids;

    protected $table = 'dispatch_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity_dispatched' => 'float', 'net_weight_kg' => 'float'];
    }

    public function dispatch(): BelongsTo { return $this->belongsTo(Dispatch::class); }
    public function packingItem(): BelongsTo { return $this->belongsTo(PackingRecordItem::class, 'packing_record_item_id'); }
    public function salesOrderLine(): BelongsTo { return $this->belongsTo(SalesOrderLine::class); }
}
