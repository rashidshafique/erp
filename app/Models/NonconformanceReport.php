<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NonconformanceReport extends Model
{
    use HasUuids;

    protected $table = 'nonconformance_reports';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime', 'target_completion_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function lot(): BelongsTo { return $this->belongsTo(CastingLot::class, 'casting_lot_id'); }
    public function inspection(): BelongsTo { return $this->belongsTo(QualityInspection::class, 'quality_inspection_id'); }
    public function defect(): BelongsTo { return $this->belongsTo(QualityDefect::class, 'quality_defect_id'); }
}
