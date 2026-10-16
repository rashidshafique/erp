<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CastingLotStageEvent extends Model
{
    use HasUuids;

    protected $table = 'casting_lot_stage_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function lot(): BelongsTo { return $this->belongsTo(CastingLot::class, 'casting_lot_id'); }
    public function stage(): BelongsTo { return $this->belongsTo(ProductionStageDefinition::class, 'stage_definition_id'); }
}
