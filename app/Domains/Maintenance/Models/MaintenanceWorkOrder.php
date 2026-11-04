<?php

namespace App\Domains\Maintenance\Models;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One maintenance job on one machine, planned or raised from a breakdown. */
class MaintenanceWorkOrder extends Model
{
    use HasUuids;

    public const STATUSES = [
        'OPEN' => 'Open', 'IN_PROGRESS' => 'In progress', 'ON_HOLD' => 'On hold',
        'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled',
    ];

    public const PRIORITIES = ['LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High', 'URGENT' => 'Urgent'];

    public const TYPES = MaintenancePlan::TYPES + ['BREAKDOWN' => 'Breakdown'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'labor_hours' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'other_cost' => 'decimal:2',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id')->withTrashed();
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(EquipmentBreakdown::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['OPEN', 'IN_PROGRESS', 'ON_HOLD']);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['OPEN', 'IN_PROGRESS', 'ON_HOLD'], true);
    }

    public function totalCost(): float
    {
        return (float) $this->parts_cost + (float) $this->other_cost;
    }

    /** in_progress, on_hold, overdue, due, completed, cancelled or planned. */
    public function dueState(): string
    {
        if ($this->status === 'IN_PROGRESS') {
            return 'in_progress';
        }
        if ($this->status === 'ON_HOLD') {
            return 'on_hold';
        }
        if ($this->status === 'COMPLETED') {
            return 'completed';
        }
        if ($this->status === 'CANCELLED') {
            return 'cancelled';
        }

        if ($this->scheduled_start_at !== null && $this->scheduled_start_at->lt(now())) {
            return 'overdue';
        }

        if ($this->plan !== null) {
            if ($this->plan->isDue()) {
                return 'overdue';
            }
            if ($this->plan->isDueSoon()) {
                return 'due';
            }
        }

        if ($this->scheduled_start_at !== null && $this->scheduled_start_at->lte(now()->addDays(MaintenancePlan::DUE_SOON_DAYS))) {
            return 'due';
        }

        return 'planned';
    }

    public function dueLabel(): string
    {
        return match ($this->dueState()) {
            'in_progress' => 'In progress',
            'on_hold' => 'On hold',
            'overdue' => 'Overdue',
            'due' => 'Due',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Planned',
        };
    }

    /** The due cell: "now" while the job runs, otherwise the scheduled day or the plan's next date or meter. */
    public function dueText(): string
    {
        if ($this->status === 'IN_PROGRESS') {
            return 'now';
        }

        if ($this->scheduled_start_at !== null) {
            return $this->scheduled_start_at->isToday() ? 'today' : $this->scheduled_start_at->displayShortDate();
        }

        $plan = $this->plan;

        if ($plan !== null && $plan->isMeterDue() && $plan->next_due_meter !== null) {
            return 'at '.\App\Support\Formats::number($plan->next_due_meter, 0).' '.($plan->meter()?->shortUnit() ?? 'units');
        }

        if ($plan?->next_due_at !== null) {
            return $plan->next_due_at->isToday() ? 'today' : $plan->next_due_at->displayShortDate();
        }

        return '—';
    }
}
