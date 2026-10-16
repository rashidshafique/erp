<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayComponent extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['default_value' => 'float', 'taxable' => 'boolean', 'prorate' => 'boolean', 'is_basic' => 'boolean', 'active' => 'boolean'];
    }
}
