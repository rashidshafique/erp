<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MouldLayer extends Model
{
    use HasUuids;

    protected $fillable = [
        'mould_assembly_id',
        'layer_number',
        'layer_type',
        'flask_reference',
        'flask_length_mm',
        'flask_width_mm',
        'flask_height_mm',
        'flask_id',
        'notes',
    ];

    protected $casts = [
        'layer_number' => 'integer',
        'flask_length_mm' => 'decimal:2',
        'flask_width_mm' => 'decimal:2',
        'flask_height_mm' => 'decimal:2',
    ];

    public function assembly(): BelongsTo
    {
        return $this->belongsTo(MouldAssembly::class, 'mould_assembly_id');
    }

    public function cavities(): HasMany
    {
        return $this->hasMany(MouldCavity::class)->orderBy('cavity_number');
    }

    public function flask(): BelongsTo
    {
        return $this->belongsTo(Flask::class)->withTrashed();
    }
}
