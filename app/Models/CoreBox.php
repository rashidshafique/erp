<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoreBox extends Model
{
    use HasUuids, SoftDeletes, \App\Models\Concerns\GeneratesCodes;

    public string $codeKind = 'core_box';

    protected $fillable = [
        'mark_code',
        'core_definition_id',
        'code',
        'name',
        'revision',
        'status',
        'material',
        'cavities',
        'nominal_core_weight_kg',
        'description',
        'notes',
        'commissioned_on',
    ];

    protected $casts = [
        'cavities' => 'integer',
        'nominal_core_weight_kg' => 'decimal:4',
        'commissioned_on' => 'date',
    ];

    public function coreDefinition(): BelongsTo
    {
        return $this->belongsTo(CoreDefinition::class);
    }
}