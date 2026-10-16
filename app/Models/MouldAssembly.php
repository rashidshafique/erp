<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class MouldAssembly extends Model
{
    use HasUuids;

    protected $fillable = [
        'assembly_number',
        'moulding_run_id',
        'mould_configuration_id',
        'status',
        'assembled_at',
        'assembled_by',
        'checked_by',
        'notes',
    ];

    protected $casts = [
        'assembled_at' => 'datetime',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(MouldConfiguration::class, 'mould_configuration_id');
    }

    public function layers(): HasMany
    {
        return $this->hasMany(MouldLayer::class)->orderBy('layer_number');
    }

    public function mouldingRun(): BelongsTo
    {
        return $this->belongsTo(MouldingRun::class);
    }

    public function pourAssignment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PourMouldAssembly::class);
    }
}
