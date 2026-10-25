<?php

namespace App\Models\Concerns;

use App\Domains\Core\Tax\TaxLine;
use App\Services\Stock\InventoryClass;
use App\Support\TaxJurisdiction;
use Illuminate\Validation\Rule;

/**
 * The tax and reporting details every stock or sales item carries: inventory class, goods or service, customs tariff code, the unit tax documents
 * count it in, and country of origin. A blank class takes the item's default; the tariff code is kept in the jurisdiction's format.
 */
trait HasTradeDetails
{
    public static function bootHasTradeDetails(): void
    {
        static::saving(function (self $item) {
            if (blank($item->supply_type)) {
                $item->supply_type = TaxLine::GOODS;
            }
            if (blank($item->inventory_class)) {
                $item->inventory_class = $item->defaultInventoryClass()->value;
            }
            $item->tariff_code = filled($item->tariff_code) ? TaxJurisdiction::format($item->tariff_code) : null;
            $item->tax_uom = filled($item->tax_uom) ? trim($item->tax_uom) : null;
            $item->origin_country = filled($item->origin_country) ? strtoupper(trim($item->origin_country)) : null;
        });
    }

    public function initializeHasTradeDetails(): void
    {
        $this->mergeFillable(['inventory_class', 'supply_type', 'tariff_code', 'tax_uom', 'origin_country']);
    }

    /** The class an item takes when none is chosen. */
    abstract public function defaultInventoryClass(): InventoryClass;

    public function inventoryClass(): InventoryClass
    {
        return InventoryClass::tryFrom((string) $this->inventory_class) ?? $this->defaultInventoryClass();
    }

    /** @return array<string, list<mixed>> */
    public static function tradeRules(): array
    {
        return [
            'inventory_class' => ['nullable', Rule::enum(InventoryClass::class)],
            'supply_type' => ['nullable', Rule::in([TaxLine::GOODS, TaxLine::SERVICE])],
            'tariff_code' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! TaxJurisdiction::isValidTariff((string) $value)) {
                    $fail('Enter the '.TaxJurisdiction::tariffLabel().' as '.TaxJurisdiction::tariffExample().'.');
                }
            }],
            'tax_uom' => ['nullable', 'string', 'max:40'],
            'origin_country' => ['nullable', 'string', 'size:2', 'alpha'],
        ];
    }
}
