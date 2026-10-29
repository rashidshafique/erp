<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouldingWorkListItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function workList(): BelongsTo { return $this->belongsTo(MouldingWorkList::class, 'moulding_work_list_id'); }
    public function team(): BelongsTo { return $this->belongsTo(MouldingTeam::class, 'moulding_team_id'); }
    public function station(): BelongsTo { return $this->belongsTo(MouldingStation::class, 'moulding_station_id'); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function patternRevision(): BelongsTo { return $this->belongsTo(PatternRevision::class); }
    public function run(): BelongsTo { return $this->belongsTo(MouldingRun::class, 'moulding_run_id'); }
    public function carriedFrom(): BelongsTo { return $this->belongsTo(self::class, 'carried_from_item_id'); }
}
