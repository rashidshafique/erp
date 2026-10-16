<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeatChargePosting extends Model
{
    use HasUuids;

    protected $fillable = [
        'heat_id',
        'status',
        'total_quantity_kg',
        'total_cost_pkr',
        'posted_by',
        'posted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_quantity_kg' => 'decimal:3',
            'total_cost_pkr' => 'decimal:4',
            'posted_at' => 'datetime',
        ];
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }
}