<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cores taken out of a lot: used in moulding, or scrapped. */
class CoreLotUse extends Model
{
    use HasUuids;

    public const KINDS = ['USED' => 'Used in moulding', 'SCRAPPED' => 'Scrapped'];

    protected $fillable = ['core_lot_id', 'quantity', 'kind', 'moulding_run_id', 'used_on', 'notes'];

    protected $casts = ['quantity' => 'integer', 'used_on' => 'date'];

    public function coreLot(): BelongsTo
    {
        return $this->belongsTo(CoreLot::class);
    }

    public function mouldingRun(): BelongsTo
    {
        return $this->belongsTo(MouldingRun::class);
    }
}
