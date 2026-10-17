<?php

namespace App\Models;

use Database\Factories\BaseSandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A refractory grain a sand system is made of, such as silica or chromite, optionally backed by a raw material in inventory. */
class BaseSand extends Model
{
    /** @use HasFactory<BaseSandFactory> */
    use Concerns\IsNamedListEntry, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'base_sands';

    protected $guarded = [];

    protected $casts = ['sort_order' => 'integer'];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function sandSystems(): BelongsToMany
    {
        return $this->belongsToMany(SandSystem::class, 'sand_system_sands')->withPivot('is_main')->withTimestamps();
    }
}
