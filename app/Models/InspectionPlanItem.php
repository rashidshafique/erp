<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionPlanItem extends Model
{
    use HasUuids;

    protected $table = 'inspection_plan_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['minimum_value' => 'float', 'maximum_value' => 'float', 'target_value' => 'float', 'is_mandatory' => 'boolean', 'is_critical' => 'boolean', 'requires_recorded_result' => 'boolean'];
    }

    public function plan(): BelongsTo { return $this->belongsTo(InspectionPlan::class, 'inspection_plan_id'); }
}
