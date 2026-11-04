<?php

namespace App\Domains\Maintenance\Models;

use App\Models\Concerns\GeneratesCodes;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A maintenance job done on a schedule: every so many days, every so many meter units, or whichever comes first.
 * Completing a work order against the plan rolls the next due date or meter forward.
 */
class MaintenancePlan extends Model
{
    use GeneratesCodes, HasUuids, SoftDeletes;

    public string $codeKind = 'maintenance_plan';

    public const DUE_SOON_DAYS = 7;

    /** The share of active plans on schedule that the design shows as the target. */
    public const COMPLIANCE_TARGET = 95;

    public const TYPES = [
        'PREVENTIVE' => 'Preventive', 'INSPECTION' => 'Inspection', 'LUBRICATION' => 'Lubrication',
        'CALIBRATION' => 'Calibration', 'REPAIR' => 'Repair',
    ];

    public const TRIGGERS = ['CALENDAR' => 'Calendar', 'METER' => 'Meter', 'BOTH' => 'Calendar or meter'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'interval_days' => 'integer',
            'interval_meter_units' => 'decimal:3',
            'estimated_duration_hours' => 'decimal:2',
            'requires_shutdown' => 'boolean',
            'active' => 'boolean',
            'last_completed_at' => 'datetime',
            'next_due_at' => 'datetime',
            'last_completed_meter' => 'decimal:3',
            'next_due_meter' => 'decimal:3',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** The meter a meter-triggered plan is measured against: the equipment's first active meter. */
    public function meter(): ?EquipmentMeter
    {
        return $this->equipment?->meters->firstWhere('active', true);
    }

    private function tracksDate(): bool
    {
        return in_array($this->trigger_type, ['CALENDAR', 'BOTH'], true);
    }

    private function tracksMeter(): bool
    {
        return in_array($this->trigger_type, ['METER', 'BOTH'], true);
    }

    public function isMeterDue(): bool
    {
        if (! $this->tracksMeter() || $this->next_due_meter === null) {
            return false;
        }

        $reading = $this->meter()?->current_reading;

        return $reading !== null && (float) $this->next_due_meter <= (float) $reading;
    }

    public function isOverdue(): bool
    {
        return $this->tracksDate() && $this->next_due_at !== null && $this->next_due_at->lte(today());
    }

    public function isDueSoon(): bool
    {
        return $this->tracksDate() && $this->next_due_at !== null
            && ! $this->isOverdue()
            && $this->next_due_at->lte(today()->addDays(self::DUE_SOON_DAYS));
    }

    /** overdue, meter_due, due_soon, not_scheduled or scheduled. */
    public function dueState(): string
    {
        if ($this->isMeterDue()) {
            return 'meter_due';
        }
        if ($this->isOverdue()) {
            return 'overdue';
        }
        if ($this->isDueSoon()) {
            return 'due_soon';
        }
        if (($this->tracksDate() ? $this->next_due_at : null) === null
            && ($this->tracksMeter() ? $this->next_due_meter : null) === null) {
            return 'not_scheduled';
        }

        return 'scheduled';
    }

    public function isDue(): bool
    {
        return in_array($this->dueState(), ['overdue', 'meter_due'], true);
    }

    public function dueLabel(): string
    {
        return match ($this->dueState()) {
            'meter_due' => 'Meter due',
            'overdue' => 'Overdue',
            'due_soon' => 'Due soon',
            'not_scheduled' => 'Not scheduled',
            default => 'Scheduled',
        };
    }

    /** "weekly", "monthly", "2,000 h" or "weekly or 400 heats", as the work order list names a plan. */
    public function cadenceLabel(): string
    {
        $parts = [];

        if (in_array($this->trigger_type, ['CALENDAR', 'BOTH'], true) && $this->interval_days !== null) {
            $parts[] = match (true) {
                $this->interval_days === 1 => 'daily',
                $this->interval_days === 7 => 'weekly',
                in_array($this->interval_days, [28, 29, 30, 31], true) => 'monthly',
                $this->interval_days === 90 => 'quarterly',
                $this->interval_days === 365 => 'yearly',
                default => 'every '.\App\Support\Formats::number($this->interval_days, 0).' days',
            };
        }

        if (in_array($this->trigger_type, ['METER', 'BOTH'], true) && $this->interval_meter_units !== null) {
            $meter = $this->meter();
            $parts[] = \App\Support\Formats::number($this->interval_meter_units, 0).' '.($meter?->shortUnit() ?? 'units');
        }

        return $parts === [] ? 'as needed' : implode(' or ', $parts);
    }
}
