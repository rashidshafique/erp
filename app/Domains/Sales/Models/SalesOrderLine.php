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
        'discount_percent',
        'uom',
        'requested_delivery_date',
        'repeat_every',
        'repeat_qty',
        'repeat_from',
        'repeat_until',
        'repeated_to',
        'notes',
    ];

    public const REPEATS = ['WEEK' => 'Every week', 'MONTH' => 'Every month'];

    protected function casts(): array
    {
        return [
            'ordered_qty' => 'decimal:3', 'unit_price' => 'decimal:4', 'discount_percent' => 'decimal:2', 'requested_delivery_date' => 'date',
            'repeat_qty' => 'decimal:3', 'repeat_from' => 'date', 'repeat_until' => 'date', 'repeated_to' => 'date',
        ];
    }

    /** The units a line can be sold in: its quantity, price and invoiced amount are all in this unit; production always makes pieces. */
    public const UNITS = ['piece' => 'pcs', 'kg' => 'kg'];

    /** The price after the line's discount: what one unit is sold for. */
    public function netUnitPrice(): float
    {
        return round((float) $this->unit_price * (1 - (float) $this->discount_percent / 100), 4);
    }

    /** What the quantity is worth at the discounted price, before tax. */
    public function netValue(float $quantity): float
    {
        return round($quantity * $this->netUnitPrice(), 2);
    }

    public function repeats(): bool
    {
        return $this->repeat_every !== null;
    }

    public function isSoldByKg(): bool
    {
        return $this->uom === 'kg';
    }

    /** The net kg of one casting: the revision's weight, else the product's nominal weight; 0 when neither is set. */
    public function pieceWeightKg(): float
    {
        return (float) ($this->productRevision?->net_weight_kg ?: $this->product?->nominal_weight_kg);
    }

    /** Pieces expressed in the line's unit. */
    public function fromPieces(float $pieces): float
    {
        return $this->isSoldByKg() ? round($pieces * $this->pieceWeightKg(), 3) : $pieces;
    }

    /** The whole pieces needed to make $quantity of the line's unit. */
    public function toPieces(float $quantity): float
    {
        if (! $this->isSoldByKg()) {
            return $quantity;
        }
        $each = $this->pieceWeightKg();

        return $each > 0 ? ceil(round($quantity / $each, 6)) : 0;
    }

    /**
     * What has been shipped on each line in the line's own unit: pieces, or for a kg line the net kg weighed at packing.
     *
     * @param  iterable<string>  $lineIds
     * @return \Illuminate\Support\Collection<string, float>
     */
    public static function shippedByLine(iterable $lineIds): \Illuminate\Support\Collection
    {
        return \Illuminate\Support\Facades\DB::table('dispatch_items')->join('sales_order_lines', 'sales_order_lines.id', '=', 'dispatch_items.sales_order_line_id')
            ->whereIn('dispatch_items.sales_order_line_id', collect($lineIds)->all())
            ->selectRaw("dispatch_items.sales_order_line_id, sum(case when sales_order_lines.uom = 'kg' then dispatch_items.net_weight_kg else dispatch_items.quantity_dispatched end) q")
            ->groupBy('dispatch_items.sales_order_line_id')->pluck('q', 'sales_order_line_id')->map(fn ($q) => (float) $q);
    }

    /**
     * The next delivery date a repeating line will add, or null when it does not repeat or its end date is passed. Dates are counted
     * from repeat_from so a month starting on the 31st keeps falling on the last day of shorter months.
     */
    public function nextRepeatDate(): ?\Carbon\CarbonImmutable
    {
        if (! $this->repeats() || ! $this->repeat_from) {
            return null;
        }
        $from = $this->repeat_from->toImmutable();
        $step = fn (int $n) => $this->repeat_every === 'MONTH' ? $from->addMonthsNoOverflow($n) : $from->addWeeks($n);
        $n = 0;
        while ($this->repeated_to && $step($n)->lte($this->repeated_to)) {
            $n++;
        }
        $next = $step($n);

        return $this->repeat_until && $next->gt($this->repeat_until) ? null : $next;
    }

    /** Whether the line will still add deliveries. */
    public function isStillRepeating(): bool
    {
        return $this->nextRepeatDate() !== null;
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
