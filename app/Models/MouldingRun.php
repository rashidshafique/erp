<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MouldingRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'run_number',
        'production_order_id',
        'pattern_revision_id',
        'mould_configuration_id',
        'run_date',
        'shift',
        'started_at',
        'completed_at',
        'moulding_method',
        'sand_system',
        'operator_name',
        'team_name',
        'moulding_team_id',
        'moulding_station_id',
        'moulding_work_list_item_id',
        'planned_mould_qty',
        'moulds_made_qty',
        'accepted_mould_qty',
        'damaged_mould_qty',
        'downtime_minutes',
        'downtime_reason',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'planned_mould_qty' => 'integer',
            'moulds_made_qty' => 'integer',
            'accepted_mould_qty' => 'integer',
            'damaged_mould_qty' => 'integer',
            'downtime_minutes' => 'integer',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function patternRevision(): BelongsTo
    {
        return $this->belongsTo(PatternRevision::class);
    }

    public function mouldConfiguration(): BelongsTo
    {
        return $this->belongsTo(MouldConfiguration::class);
    }
    public function mouldAssemblies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MouldAssembly::class)->orderBy('assembly_number');
    }

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MouldingTeam::class, 'moulding_team_id');
    }

    public function station(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MouldingStation::class, 'moulding_station_id');
    }

    public function workListItem(): BelongsTo { return $this->belongsTo(MouldingWorkListItem::class, 'moulding_work_list_item_id'); }
}
