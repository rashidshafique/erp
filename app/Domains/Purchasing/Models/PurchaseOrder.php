<?php

namespace App\Domains\Purchasing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An order to a supplier. Draft until issued; receiving against it makes material lots in quarantine. */
class PurchaseOrder extends Model
{
    use HasUuids;

    public const STATUSES = [
        'DRAFT' => 'Draft', 'ISSUED' => 'Issued', 'PART_RECEIVED' => 'Part received', 'RECEIVED' => 'Received', 'CLOSED' => 'Closed', 'CANCELLED' => 'Cancelled',
    ];

    /** Statuses in which goods may still be received. */
    public const RECEIVING = ['ISSUED', 'PART_RECEIVED'];

    /** Statuses of an order not yet finished: still a draft or waiting for goods. */
    public const OPEN = ['DRAFT', 'ISSUED', 'PART_RECEIVED'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_date' => 'date', 'issued_at' => 'datetime', 'closed_at' => 'datetime',
            'subtotal' => 'float', 'tax_total' => 'float', 'total' => 'float'];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class)->withTrashed(); }
    public function lines(): HasMany { return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_number'); }

    public function canReceive(): bool
    {
        return in_array($this->status, self::RECEIVING, true);
    }

    public function isLate(): bool
    {
        return $this->canReceive() && $this->expected_date !== null && $this->expected_date->lt(today());
    }
}
