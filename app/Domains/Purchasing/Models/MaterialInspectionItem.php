<?php

namespace App\Domains\Purchasing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** One characteristic checked when a lot of this material arrives, with its limits. */
class MaterialInspectionItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['min_value' => 'decimal:4', 'max_value' => 'decimal:4'];
    }
}
