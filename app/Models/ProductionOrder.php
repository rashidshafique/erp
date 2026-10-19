<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'production_order_number',
        'sales_order_line_id',
        'product_id',
        'product_revision_id',
        'manufacturing_method_id',
        'process_route_id',
        'order_type',
        'status',
        'priority',
        'planned_qty',
        'released_qty',
        'completed_qty',
        'rejected_qty',
        'uom',
        'planned_start_date',
        'planned_finish_date',
        'required_date',
        'actual_started_at',
        'actual_completed_at',
        'instructions',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_qty' => 'decimal:3',
            'released_qty' => 'decimal:3',
            'completed_qty' => 'decimal:3',
            'rejected_qty' => 'decimal:3',
            'planned_start_date' => 'date',
            'planned_finish_date' => 'date',
            'required_date' => 'date',
            'actual_started_at' => 'datetime',
            'actual_completed_at' => 'datetime',
        ];
    }

    /** The customer this order is for (through its sales order line), or null for a stock order. */
    public function customer(): ?Customer
    {
        return $this->salesOrderLine?->salesOrder?->customer;
    }

    public function customerLabel(): string
    {
        $c = $this->customer();

        return $c ? ($c->short_name ?: $c->name) : 'Stock';
    }

    public function castingLots(): HasMany
    {
        return $this->hasMany(CastingLot::class);
    }

    public function mouldingRuns(): HasMany
    {
        return $this->hasMany(MouldingRun::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();   // archived products still show on old records
    }

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }

    public function manufacturingMethod(): BelongsTo
    {
        return $this->belongsTo(ManufacturingMethod::class);
    }

    public function processRoute(): BelongsTo
    {
        return $this->belongsTo(ProcessRoute::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(
            ProductionOrderOperation::class
        )->orderBy('sequence');
    }

    /** A released order is in progress from the first moulding run; later runs and pours leave it as it is. */
    public function markInProgress(): void
    {
        if ($this->status === 'RELEASED') {
            $this->update(['status' => 'IN_PROGRESS', 'actual_started_at' => $this->actual_started_at ?? now()]);
        }
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [
            'COMPLETED',
            'CANCELLED',
        ]);
    }
}
