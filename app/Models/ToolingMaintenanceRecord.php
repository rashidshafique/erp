<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToolingMaintenanceRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'tooling_type',
        'pattern_id',
        'pattern_revision_id',
        'core_definition_id',
        'core_box_id',
        'mould_configuration_id',
        'flask_id',
        'mould_assembly_id',
        'record_number',
        'activity_type',
        'status',
        'priority',
        'reported_at',
        'started_at',
        'completed_at',
        'reported_by',
        'performed_by',
        'problem_description',
        'inspection_findings',
        'work_performed',
        'parts_used',
        'notes',
        'labour_hours',
        'parts_cost',
        'other_cost',
        'downtime_hours',
        'next_inspection_due_on',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'next_inspection_due_on' => 'date',
            'labour_hours' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'other_cost' => 'decimal:2',
            'downtime_hours' => 'decimal:2',
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

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'attachable_id')
            ->where('attachable_type', 'tooling_maintenance')
            ->whereNull('removed_at')
            ->latest('created_at');
    }

    public function flask(): BelongsTo
    {
        return $this->belongsTo(Flask::class)->withTrashed();
    }
}
