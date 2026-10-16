<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CharacteristicLimit extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['min_value' => 'float', 'max_value' => 'float', 'is_required_for_release' => 'boolean'];
    }
}
