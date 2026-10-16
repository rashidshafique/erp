<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThermalCalibration extends Model
{
    use HasUuids;

    protected $fillable = [
        'cup_type',
        'tl_intercept_c',
        'tl_slope_c_per_ce',
        'inoculant_gain_c_per_0p1pct',
        'sample_count',
        'r_squared',
        'residual_sd_ce',
        'fitted_at',
    ];

    protected function casts(): array
    {
        return ['fitted_at' => 'datetime'];
    }
}
