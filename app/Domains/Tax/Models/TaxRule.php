<?php

namespace App\Domains\Tax\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * When taxes apply to a line: on sales, purchases or both, and under conditions on the line and the other party. Every rule whose
 * conditions all hold adds its taxes, in priority order; a tax added twice counts once. A condition left out matches anything.
 *
 * Conditions: supply_type (GOODS|SERVICE), regions (place of supply), party_status (list), withholding_agent (bool),
 * inventory_classes (list), code_prefix (start of the tariff or service code).
 */
class TaxRule extends Model
{
    use HasUuids;

    public const SIDES = ['BOTH' => 'Sales and purchases', 'SALE' => 'Sales', 'PURCHASE' => 'Purchases'];

    protected $fillable = ['jurisdiction', 'name', 'priority', 'side', 'conditions', 'components', 'effective_from', 'effective_to', 'is_active'];

    protected function casts(): array
    {
        return ['conditions' => 'array', 'components' => 'array', 'effective_from' => 'date', 'effective_to' => 'date', 'is_active' => 'boolean'];
    }
}
