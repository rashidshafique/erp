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
        'quality_defect_id',
        'cavity_numbers',
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
            'cavity_numbers' => 'array',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    /** The next run number of the day, MR-20261017-004. Call inside a transaction: it locks the day's runs. */
    public static function nextRunNumber(): string
    {
        $prefix = 'MR-'.now()->format('Ymd').'-';
        $lastNumber = static::query()
            ->where('run_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('run_number')
            ->map(fn ($number) => (int) substr($number, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(QualityDefect::class, 'quality_defect_id');
    }

    public function patternRevision(): BelongsTo
    {
        return $this->belongsTo(PatternRevision::class);
    }

    public function mouldConfiguration(): BelongsTo
    {
        return $this->belongsTo(MouldConfiguration::class);
    }

    public function mouldAssemblies(): HasMany
    {
        return $this->hasMany(MouldAssembly::class)->orderBy('assembly_number');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(MouldingTeam::class, 'moulding_team_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(MouldingStation::class, 'moulding_station_id');
    }

    public function workListItem(): BelongsTo
    {
        return $this->belongsTo(MouldingWorkListItem::class, 'moulding_work_list_item_id');
    }
}
