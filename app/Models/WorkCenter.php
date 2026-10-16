<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCenter extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'work_center_type',
        'department',
        'status',
        'capacity_hours_per_day',
        'efficiency_percent',
        'description',
    ];

    protected $casts = [
        'capacity_hours_per_day' => 'decimal:2',
        'efficiency_percent' => 'decimal:2',
    ];

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }
}