<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolingUsageRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'pattern_id',
        'pattern_revision_id',
        'core_definition_id',
        'core_box_id',
        'mould_configuration_id',
        'mould_assembly_id',
        'tooling_type',
        'usage_quantity',
        'usage_unit',
        'used_at',
        'reference_type',
        'reference_id',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'usage_quantity' => 'decimal:3',
            'used_at' => 'datetime',
        ];
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(Pattern::class)->withTrashed();   // archived patterns still show on old records
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
