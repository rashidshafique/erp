<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityInspectionResult extends Model
{
    use HasUuids;

    protected $table = 'quality_inspection_results';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'tested_at' => 'datetime', 'measured_value' => 'float', 'minimum_value' => 'float', 'maximum_value' => 'float'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(QualityInspection::class, 'quality_inspection_id');
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(QualityDefect::class, 'quality_defect_id');
    }

    public function gauge(): BelongsTo
    {
        return $this->belongsTo(Gauge::class)->withTrashed();
    }

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(InspectionPlanItem::class, 'inspection_plan_item_id');
    }
}
