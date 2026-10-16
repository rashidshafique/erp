<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionStageDefinition extends Model
{
    use HasUuids;

    protected $table = 'production_stage_definitions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_quality_gate' => 'boolean', 'requires_equipment' => 'boolean', 'requires_quantity_confirmation' => 'boolean', 'is_active' => 'boolean'];
    }

}
