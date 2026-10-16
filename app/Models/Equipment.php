<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = [
        'code',
        'name',
        'equipment_type',
        'manufacturer',
        'model',
        'serial_number',
        'status',
        'commissioned_on',
        'warranty_until',
        'rated_power_kw',
        'rated_capacity_kg',
        'location_description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'commissioned_on' => 'date',
            'warranty_until' => 'date',
            'rated_power_kw' => 'decimal:3',
            'rated_capacity_kg' => 'decimal:3',
        ];
    }

    public function furnace(): HasOne
    {
        return $this->hasOne(Furnace::class);
    }
}
