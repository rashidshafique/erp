<?php

namespace App\Domains\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmployeeBenefit extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['monthly_value' => 'float', 'taxable' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date'];
    }
}
