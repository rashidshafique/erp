<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pieces of a lot found with a defect at inspection, by pattern cavity when known, rejected or sent to rework. */
class QualityDefectMark extends Model
{
    use HasUuids;

    public const DISPOSITIONS = ['REJECT' => 'Reject', 'REWORK' => 'Rework'];

    protected $guarded = [];

    public function defect(): BelongsTo
    {
        return $this->belongsTo(QualityDefect::class, 'quality_defect_id');
    }

    public function patternRevision(): BelongsTo
    {
        return $this->belongsTo(PatternRevision::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(CastingLot::class, 'casting_lot_id');
    }

    /** "MP-7 cavity 5", or the pattern alone, or null when the cavity was not recorded. */
    public function cavityLabel(): ?string
    {
        $pattern = $this->patternRevision?->pattern?->code;

        return $this->cavity_number ? trim(($pattern ?? '').' cavity '.$this->cavity_number) : $pattern;
    }
}
