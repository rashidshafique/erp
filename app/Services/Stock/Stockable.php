<?php

namespace App\Services\Stock;

/** Something that can be kept in stock and moved through the ledger: a material today, finished goods or consumables later. */
interface Stockable
{
    /** The key this kind of item is registered under in {@see StockItemTypes}, stored with every movement of it. */
    public function stockItemType(): string;

    public function stockItemId(): string;

    public function stockCode(): string;

    public function stockName(): string;

    /** The unit its quantities are counted in. */
    public function stockUom(): string;

    public function isStockTracked(): bool;

    public function isLotTracked(): bool;

    public function standardCost(): ?float;

    /** The line of the accounts its stock is reported in, unless a movement says otherwise. */
    public function inventoryClass(): InventoryClass;
}
