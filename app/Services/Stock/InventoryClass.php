<?php

namespace App\Services\Stock;

/**
 * The line of the accounts an item's stock and cost are reported in. Financial statements keep goods bought for resale
 * (stock-in-trade) apart from raw materials used in production and from the finished goods made.
 */
enum InventoryClass: string
{
    case RawMaterial = 'RAW_MATERIAL';
    case FinishedGoods = 'FINISHED_GOODS';
    case StockInTrade = 'STOCK_IN_TRADE';
    case Consumable = 'CONSUMABLE';
    case StoresSpares = 'STORES_SPARES';

    public function label(): string
    {
        return match ($this) {
            self::RawMaterial => 'Raw material',
            self::FinishedGoods => 'Finished goods',
            self::StockInTrade => 'Stock-in-trade (bought for resale)',
            self::Consumable => 'Consumable',
            self::StoresSpares => 'Stores and spares',
        };
    }

    /** The class a new material starts in: fuel and sundries are consumed, everything else goes into the castings. */
    public static function forMaterialType(?string $materialType): self
    {
        return in_array($materialType, ['FUEL', 'OTHER'], true) ? self::Consumable : self::RawMaterial;
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $class) => [$class->value => $class->label()])->all();
    }
}
