<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessControlPlanItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['requires_recorded_result' => 'boolean', 'is_mandatory' => 'boolean', 'is_critical' => 'boolean'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProcessControlPlan::class, 'process_control_plan_id');
    }

    public function stageDefinition(): BelongsTo
    {
        return $this->belongsTo(ProductionStageDefinition::class, 'stage_definition_id');
    }
}
