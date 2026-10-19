<?php

namespace App\Domains\Purchasing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IncomingInspectionResult extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['min_value' => 'decimal:4', 'max_value' => 'decimal:4', 'measured_value' => 'decimal:4', 'passed' => 'boolean'];
    }
}
