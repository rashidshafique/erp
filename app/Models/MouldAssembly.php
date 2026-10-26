<?php

namespace App\Models;

use App\Services\Production\FlaskManager;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'is_flaskless',
        'flask_size_id',
        'flasks_freed',
    ];

    protected $casts = [
        'assembled_at' => 'datetime',
        'is_flaskless' => 'boolean',
    ];

    protected static function booted(): void
    {
        // A mould that is scrapped or cancelled gives its flasks back; one that is poured does too when the rule says so.
        static::updated(function (MouldAssembly $mould) {
            if ($mould->wasChanged('status')) {
                app(FlaskManager::class)->mouldStatusChanged($mould);
            }
        });
    }

    public function flaskSize(): BelongsTo
    {
        return $this->belongsTo(FlaskSize::class)->withTrashed();
    }

    /** Flasks holding this mould right now. */
    public function flasks(): HasMany
    {
        return $this->hasMany(Flask::class, 'current_mould_assembly_id');
    }

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

    public function pourAssignment(): HasOne
    {
        return $this->hasOne(PourMouldAssembly::class);
    }
}
