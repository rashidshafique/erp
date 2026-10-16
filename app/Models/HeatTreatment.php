<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HeatTreatment extends Model
{
    use HasUuids;

    public const TYPES = [
        'MG_TREATMENT' => 'Magnesium treatment', 'INOCULATION_LADLE' => 'Inoculation (ladle)', 'INOCULATION_STREAM' => 'Inoculation (stream)',
        'INOCULATION_INMOULD' => 'Inoculation (in-mould)', 'DESULPHURISATION' => 'Desulphurisation', 'CARBON_ADJUST' => 'Carbon / alloy adjustment', 'OTHER' => 'Other',
    ];
    public const METHODS = ['SANDWICH' => 'Sandwich', 'TUNDISH' => 'Tundish cover', 'PLUNGING' => 'Plunging', 'CORED_WIRE' => 'Cored wire', 'STREAM' => 'Stream', 'INMOULD' => 'In-mould', 'OTHER' => 'Other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['treated_at' => 'datetime', 'amount_kg' => 'float', 'metal_treated_kg' => 'float', 'addition_pct' => 'float', 'temperature_c' => 'float', 'recovery_pct' => 'float'];
    }

    public function isInoculation(): bool
    {
        return str_starts_with($this->treatment_type, 'INOCULATION');
    }
}
