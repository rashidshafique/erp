<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderOperation extends Model
{
    use HasUuids;

    protected $fillable = [
        'production_order_id',
        'operation_id',
        'sequence',
        'status',
        'planned_qty',
        'started_qty',
        'completed_qty',
        'rejected_qty',
        'planned_start_at',
        'planned_end_at',
        'actual_start_at',
        'actual_end_at',
        'assigned_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'planned_qty' => 'decimal:3',
            'started_qty' => 'decimal:3',
            'completed_qty' => 'decimal:3',
            'rejected_qty' => 'decimal:3',
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }
}
