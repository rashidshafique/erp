<?php

namespace App\Domains\Cameras\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One channel of a recorder, with the name and area it is shown under. */
class Camera extends Model
{
    use HasUuids;

    protected $fillable = ['dvr_id', 'channel', 'name', 'area', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'channel' => 'integer', 'sort_order' => 'integer'];
    }

    public function dvr(): BelongsTo
    {
        return $this->belongsTo(Dvr::class);
    }

    /** Cameras that are on and whose recorder is on, in the order they are shown. */
    public function scopeShown(Builder $query): Builder
    {
        return $query->where('active', true)->whereHas('dvr', fn ($q) => $q->where('active', true))->orderBy('sort_order')->orderBy('name');
    }
}
