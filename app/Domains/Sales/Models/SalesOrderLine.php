<?php

namespace App\Domains\Sales\Models;

use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
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

    public function deliveries(): HasMany
    {
        return $this->hasMany(SalesOrderLineDelivery::class)->orderBy('due_date');
    }

    /**
     * Each tranche with how much of it the shipped quantity covers, oldest tranche first.
     *
     * @return \Illuminate\Support\Collection<int, array{delivery: SalesOrderLineDelivery, covered: float, overdue: bool}>
     */
    public function deliveryProgress(float $shipped): \Illuminate\Support\Collection
    {
        return $this->deliveries->map(function (SalesOrderLineDelivery $delivery) use (&$shipped) {
            $covered = min($shipped, (float) $delivery->quantity);
            $shipped -= $covered;

            return ['delivery' => $delivery, 'covered' => $covered, 'overdue' => $covered < (float) $delivery->quantity - 0.0005 && $delivery->due_date->lt(today())];
        });
    }
}
