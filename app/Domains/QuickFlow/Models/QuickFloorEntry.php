<?php

namespace App\Domains\QuickFlow\Models;

use App\Models\Heat;
use App\Models\MouldingRun;
use App\Models\Pour;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A count typed in on the quick floor page: moulds made for an order, or moulds poured from a heat. */
class QuickFloorEntry extends Model
{
    use HasUuids;

    public const MADE = 'MADE';

    public const POURED = 'POURED';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'moulds' => 'integer', 'pieces_per_mould' => 'integer'];
    }

    public function pieces(): int
    {
        return $this->moulds * $this->pieces_per_mould;
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }

    public function mouldingRun(): BelongsTo
    {
        return $this->belongsTo(MouldingRun::class);
    }

    public function pour(): BelongsTo
    {
        return $this->belongsTo(Pour::class);
    }
}
