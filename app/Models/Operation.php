<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operation extends Model
{
    use HasUuids;

    protected $fillable = [
        'process_route_id',
        'work_center_id',
        'stage_definition_id',
        'sequence',
        'code',
        'name',
        'operation_type',
        'setup_time_minutes',
        'run_time_minutes',
        'queue_time_minutes',
        'move_time_minutes',
        'standard_labor_hours',
        'standard_machine_hours',
        'is_subcontracted',
        'is_optional',
        'instructions',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'setup_time_minutes' => 'decimal:2',
        'run_time_minutes' => 'decimal:4',
        'queue_time_minutes' => 'decimal:2',
        'move_time_minutes' => 'decimal:2',
        'standard_labor_hours' => 'decimal:4',
        'standard_machine_hours' => 'decimal:4',
        'is_subcontracted' => 'boolean',
        'is_optional' => 'boolean',
    ];

    public function processRoute(): BelongsTo
    {
        return $this->belongsTo(ProcessRoute::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    /** The library step this route step uses. */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProductionStageDefinition::class, 'stage_definition_id');
    }
}