<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MouldConfiguration extends Model
{
    use HasUuids, SoftDeletes, \App\Models\Concerns\GeneratesCodes;

    public string $codeKind = 'mould_configuration';

    protected $fillable = [
        'mark_code',
        'code',
        'name',
        'mould_type',
        'nominal_layer_count',
        'description',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'nominal_layer_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function assemblies(): HasMany
    {
        return $this->hasMany(MouldAssembly::class);
    }
}
