<?php

namespace App\Domains\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rate_percent' => 'float', 'is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
