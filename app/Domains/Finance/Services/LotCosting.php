<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\LotCostAdjustment;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierBillLine;
use App\Domains\Purchasing\Models\PurchaseOrderLine;
use App\Models\MaterialLot;
use App\Models\StockLotCostLayer;

/**
 * Lots received against a purchase order are costed at the order price. Once the supplier bills the line, the billed price (net of
 * tax, weighted over every posted bill of that line) becomes the cost of the line's lots and of what is left of them in stock, so
 * later issues carry what was really paid. Stock already used keeps the cost it went out at; the difference is recorded.
 * Supplier credit notes are not counted. Nothing is posted: stock is costed periodically in the ledger.
 */
class LotCosting
{
    /** Re-costs the lots of every order line on the bill. Returns how many lots changed. */
    public function refresh(SupplierBill $bill, string $reason = 'BILLED'): int
    {
        if (FinanceRules::billPriceToCost() === 'OFF' || $bill->isCreditNote()) {
            return 0;
        }
        $changed = 0;
        foreach ($bill->lines()->whereNotNull('purchase_order_line_id')->pluck('purchase_order_line_id')->unique() as $orderLineId) {
            $price = $this->billedPrice($orderLineId);
            foreach (MaterialLot::where('purchase_order_line_id', $orderLineId)->get() as $lot) {
                $changed += $this->recost($lot, $price, $bill, $reason) ? 1 : 0;
            }
        }

        return $changed;
    }

    /** The order line's unit price as billed: net over quantity of its lines on posted bills, or the order price if none. */
    public function billedPrice(string $orderLineId): float
    {
        $billed = SupplierBillLine::where('purchase_order_line_id', $orderLineId)
            ->whereHas('bill', fn ($q) => $q->where('status', 'POSTED')->where('kind', 'BILL'))
            ->selectRaw('coalesce(sum(line_net), 0) as net, coalesce(sum(quantity), 0) as qty')->first();

        return (float) $billed->qty > 0 ? round((float) $billed->net / (float) $billed->qty, 4) : (float) PurchaseOrderLine::whereKey($orderLineId)->value('unit_price');
    }

    private function recost(MaterialLot $lot, float $price, SupplierBill $bill, string $reason): bool
    {
        $from = round((float) $lot->unit_cost, 4);
        if (abs($from - $price) < 0.00005) {
            return false;
        }
        $layers = StockLotCostLayer::where('material_lot_id', $lot->id)->get();
        $onHand = round((float) $layers->sum('remaining_qty'), 3);
        $used = round((float) $layers->sum(fn ($l) => (float) $l->original_qty - (float) $l->remaining_qty), 3);
        LotCostAdjustment::create([
            'material_lot_id' => $lot->id, 'supplier_bill_id' => $bill->id, 'reason' => $reason, 'from_unit_cost' => $from, 'to_unit_cost' => $price,
            'quantity_on_hand' => $onHand, 'quantity_used' => max(0, $used), 'variance_on_used' => round(max(0, $used) * ($price - $from), 2),
        ]);
        $lot->update(['unit_cost' => $price]);
        StockLotCostLayer::where('material_lot_id', $lot->id)->where('remaining_qty', '>', 0)->update(['unit_cost' => $price]);

        return true;
    }
}
