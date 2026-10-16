<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CustomFieldDefinition extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean', 'is_active' => 'boolean', 'min_value' => 'float', 'max_value' => 'float'];
    }
}
