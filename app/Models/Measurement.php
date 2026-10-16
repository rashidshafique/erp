<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'float', 'min_value' => 'float', 'max_value' => 'float', 'passed' => 'boolean'];
    }
}
