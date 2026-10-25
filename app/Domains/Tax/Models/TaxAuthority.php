<?php

namespace App\Domains\Tax\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A body that collects tax, federal or for one region, such as FBR or the Sindh Revenue Board. `is_registered` says the company files with it. */
class TaxAuthority extends Model
{
    use HasUuids;

    protected $fillable = ['jurisdiction', 'code', 'name', 'level', 'region', 'registration_label', 'is_registered'];

    protected function casts(): array
    {
        return ['is_registered' => 'boolean'];
    }

    public function components(): HasMany
    {
        return $this->hasMany(TaxComponent::class);
    }
}
