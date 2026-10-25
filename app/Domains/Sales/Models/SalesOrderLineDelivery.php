<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One tranche of a staggered delivery: a quantity of the line promised for a date. A line with none is delivered in one go on its own date. */
class SalesOrderLineDelivery extends Model
{
    use HasUuids;

    protected $fillable = ['sales_order_line_id', 'due_date', 'quantity', 'notes'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'quantity' => 'decimal:3'];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }
}
