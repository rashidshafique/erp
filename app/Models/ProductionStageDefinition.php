<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A step a casting can go through after the pour, from the foundry's library of steps. What a step does is set by its kind
 * and behaviours, not its code, so a foundry can name and add steps freely. Routes put steps in order.
 */
class ProductionStageDefinition extends Model
{
    use HasUuids;

    public const KINDS = [
        'PROCESS' => 'Work on the castings',
        'INSPECTION' => 'Inspection or test',
        'WEIGH' => 'Weighing',
        'OUTSIDE' => 'Done outside (subcontract)',
        'WAIT' => 'Waiting time (cooling, curing)',
    ];

    protected $table = 'production_stage_definitions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_quality_gate' => 'boolean', 'knocks_out_mould' => 'boolean', 'records_weight' => 'boolean', 'requires_equipment' => 'boolean',
            'requires_quantity_confirmation' => 'boolean', 'is_active' => 'boolean',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(CastingLotStageEvent::class, 'stage_definition_id');
    }
}
