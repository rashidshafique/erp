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
            'ordered_qty' => 'decimal:3', 'unit_price' => 'decimal:4', 'requested_delivery_date' => 'date',
            'repeat_qty' => 'decimal:3', 'repeat_from' => 'date', 'repeat_until' => 'date', 'repeated_to' => 'date',
        ];
    }

    public function repeats(): bool
    {
        return $this->repeat_every !== null;
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
