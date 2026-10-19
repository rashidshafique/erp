<?php

namespace App\Domains\Hr\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HrLocation extends Model
{
    use GeneratesCodes, HasUuids;

    protected $guarded = [];

    public string $codeKind = 'hr_location';

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
