<?php

namespace App\Domains\Purchasing\Models;

use App\Models\MaterialLot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An inspection of one received lot. The limits are copied onto each result so a later change to the plan does not rewrite history. */
class IncomingInspection extends Model
{
    use HasUuids;

    public const RESULTS = ['PASS' => 'Passed', 'FAIL' => 'Failed', 'CONCESSION' => 'Accepted by concession'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['inspected_on' => 'date'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(IncomingInspectionResult::class);
    }
}
