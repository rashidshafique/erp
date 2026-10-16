<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackingRecordItem extends Model
{
    use HasUuids;

    protected $table = 'packing_record_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity_packed' => 'float', 'net_weight_kg' => 'float'];
    }

    public function packing(): BelongsTo { return $this->belongsTo(PackingRecord::class, 'packing_record_id'); }
    public function lot(): BelongsTo { return $this->belongsTo(CastingLot::class, 'casting_lot_id'); }
    public function dispatchItems(): HasMany { return $this->hasMany(DispatchItem::class); }
}
