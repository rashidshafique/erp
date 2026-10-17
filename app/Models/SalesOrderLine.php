<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'product_revision_id',
        'line_number',
        'ordered_qty',
        'unit_price',
        'uom',
        'requested_delivery_date',
        'notes',
    ];

    protected function casts(): array
    {
        return ['ordered_qty' => 'decimal:3', 'unit_price' => 'decimal:4', 'requested_delivery_date' => 'date'];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();   // archived products still show on old records
    }

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
