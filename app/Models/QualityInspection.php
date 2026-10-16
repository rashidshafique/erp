<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityInspection extends Model
{
    use HasUuids;

    protected $table = 'quality_inspections';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['inspected_at' => 'datetime'];
    }

    public function lot(): BelongsTo { return $this->belongsTo(CastingLot::class, 'casting_lot_id'); }
    public function stage(): BelongsTo { return $this->belongsTo(ProductionStageDefinition::class, 'stage_definition_id'); }
    public function results(): HasMany { return $this->hasMany(QualityInspectionResult::class)->orderBy('sequence'); }
    public function ncrs(): HasMany { return $this->hasMany(NonconformanceReport::class); }
}
