<?php

namespace App\Services\Stock;

/** A batch of a stockable item that the ledger receives into and issues from. */
interface StockLot
{
    public function stockItem(): Stockable;

    public function stockLotId(): string;

    public function stockLotNumber(): string;
}
