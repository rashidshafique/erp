<?php

namespace App\Models;

use App\Domains\Maintenance\Models\EquipmentBreakdown;
use App\Domains\Maintenance\Models\EquipmentMeter;
use App\Domains\Maintenance\Models\MaintenancePlan;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use HasUuids;
    use SoftDeletes;

    public const TYPES = [
        'FURNACE' => 'Furnace', 'MOULDING_LINE' => 'Moulding line', 'SAND_MIXER' => 'Sand mixer',
        'SAND_SYSTEM' => 'Sand system', 'CORE_MACHINE' => 'Core machine', 'SHOT_BLAST' => 'Shot blast',
        'CRANE' => 'Crane', 'COMPRESSOR' => 'Compressor', 'LAB' => 'Lab equipment', 'OTHER' => 'Other',
    ];

    public const STATUSES = [
        'AVAILABLE' => 'Available', 'IN_USE' => 'In use', 'MAINTENANCE' => 'Under maintenance',
        'BREAKDOWN' => 'Breakdown', 'RETIRED' => 'Retired', 'DISPOSED' => 'Disposed',
    ];

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

    public function meters(): HasMany
    {
        return $this->hasMany(EquipmentMeter::class)->orderBy('meter_code');
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    public function maintenanceWorkOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class);
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(EquipmentBreakdown::class);
    }
}
