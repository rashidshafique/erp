<?php

namespace App\Domains\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HrHoliday extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['holiday_on' => 'date'];
    }
}
