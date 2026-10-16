<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoreDefinition extends Model
{
    use HasUuids, SoftDeletes, \App\Models\Concerns\GeneratesCodes;

    public string $codeKind = 'core_definition';

    protected $fillable = [
        'mark_code',
        'code',
        'name',
        'core_type',
        'status',
        'nominal_weight_kg',
        'process_type',
        'description',
        'engineering_notes',
    ];

    protected $casts = [
        'nominal_weight_kg' => 'decimal:4',
    ];

    public function coreBoxes(): HasMany
    {
        return $this->hasMany(CoreBox::class);
    }
}