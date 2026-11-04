<?php

namespace App\Domains\Maintenance\Models;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A machine that stopped: when it started, how bad it is, and how long it was down. */
class EquipmentBreakdown extends Model
{
    use HasUuids;

    public const SEVERITIES = ['MINOR' => 'Minor', 'MAJOR' => 'Major', 'CRITICAL' => 'Critical'];

    public const STATUSES = ['OPEN' => 'Open', 'IN_REPAIR' => 'In repair', 'RESTORED' => 'Restored'];

    public const CATEGORIES = [
        'MECHANICAL' => 'Mechanical', 'ELECTRICAL' => 'Electrical', 'HYDRAULIC' => 'Hydraulic',
        'PNEUMATIC' => 'Pneumatic', 'STRUCTURAL' => 'Structural', 'CONTROL' => 'Control / instrumentation', 'OTHER' => 'Other',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'reported_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWorkOrder::class, 'maintenance_work_order_id');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['OPEN', 'IN_REPAIR']);
    }

    public function isOpen(): bool
    {
        return $this->restored_at === null;
    }

    /** Hours from the stop to the restore; an unresolved breakdown counts to now. */
    public function downtimeHours(): float
    {
        $end = $this->restored_at ?? now();

        return max(0, $this->started_at->diffInMinutes($end) / 60);
    }
}
