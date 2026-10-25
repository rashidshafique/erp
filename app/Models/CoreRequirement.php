<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product revision needs this core, this many to every casting. One core can serve several products. */
class CoreRequirement extends Model
{
    use HasUuids;

    protected $fillable = ['core_definition_id', 'product_revision_id', 'quantity_per_casting', 'notes'];

    protected $casts = ['quantity_per_casting' => 'integer'];

    public function coreDefinition(): BelongsTo
    {
        return $this->belongsTo(CoreDefinition::class)->withTrashed();
    }

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }
}
