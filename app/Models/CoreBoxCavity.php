<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One impression of a core box and the core it makes. Boxes with several cores have different cores in different cavities. */
class CoreBoxCavity extends Model
{
    use HasUuids;

    protected $fillable = ['core_box_id', 'cavity_number', 'core_definition_id', 'notes'];

    protected $casts = ['cavity_number' => 'integer'];

    public function coreBox(): BelongsTo
    {
        return $this->belongsTo(CoreBox::class);
    }

    public function coreDefinition(): BelongsTo
    {
        return $this->belongsTo(CoreDefinition::class)->withTrashed();
    }
}
