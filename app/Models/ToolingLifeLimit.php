<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolingLifeLimit extends Model
{
    use HasUuids;

    protected $fillable = [
        'tooling_type',
        'pattern_id',
        'pattern_revision_id',
        'core_definition_id',
        'core_box_id',
        'mould_configuration_id',
        'mould_assembly_id',
        'usage_unit',
        'service_life_quantity',
        'inspection_interval_quantity',
        'usage_at_last_inspection',
        'last_inspected_at',
        'warning_threshold_percent',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'service_life_quantity' => 'decimal:3',
            'inspection_interval_quantity' => 'decimal:3',
            'usage_at_last_inspection' => 'decimal:3',
            'last_inspected_at' => 'datetime',
            'warning_threshold_percent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class);
    }

    public function patternRevision(): BelongsTo
    {
        return $this->belongsTo(PatternRevision::class);
    }

    public function coreDefinition(): BelongsTo
    {
        return $this->belongsTo(CoreDefinition::class);
    }

    public function coreBox(): BelongsTo
    {
        return $this->belongsTo(CoreBox::class);
    }

    public function mouldConfiguration(): BelongsTo
    {
        return $this->belongsTo(MouldConfiguration::class);
    }

    public function mouldAssembly(): BelongsTo
    {
        return $this->belongsTo(MouldAssembly::class);
    }
}