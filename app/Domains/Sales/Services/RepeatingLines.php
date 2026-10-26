<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Services\Orders\ProductionOrderFactory;
use App\Support\OrderRules;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a repeating order line scheduled for customers who take a weekly or monthly quantity without sending a PO each time. Each
 * period up to the repeat-ahead rule adds a delivery of repeat_qty and grows the ordered quantity by it; the first delivery is always
 * added so a new line is never empty. On a live order with the straight-to-production flow the new quantity is released at once.
 * A delivery removed by hand is not added back: repeated_to remembers the last date scheduled.
 */
class RepeatingLines
{
    /** @return int Deliveries added. */
    public function topUp(SalesOrderLine $line): int
    {
        return DB::transaction(function () use ($line) {
            $line = SalesOrderLine::with('salesOrder')->lockForUpdate()->findOrFail($line->id);
            if (! in_array($line->salesOrder->status, SalesOrder::OPEN, true) || $line->salesOrder->status === 'ON_HOLD') {
                return 0;
            }
            $horizon = today()->addDays(OrderRules::repeatAheadDays());
            $added = 0;
            while (($next = $line->nextRepeatDate()) && ($next->lte($horizon) || ! $line->repeated_to)) {
                $line->deliveries()->create(['due_date' => $next, 'quantity' => $line->repeat_qty, 'notes' => 'Repeat']);
                $line->ordered_qty = (float) $line->ordered_qty + (float) $line->repeat_qty;
                $line->repeated_to = $next;
                $line->save();
                $added++;
            }
            if ($added && $line->salesOrder->status === 'IN_PRODUCTION' && OrderRules::sendsStraightToProduction()) {
                app(ProductionOrderFactory::class)->releaseForLine($line);
            }

            return $added;
        });
    }

    /** @return int Deliveries added across every open order. */
    public function topUpAll(): int
    {
        return SalesOrderLine::whereNotNull('repeat_every')
            ->whereHas('salesOrder', fn ($q) => $q->whereIn('status', array_diff(SalesOrder::OPEN, ['ON_HOLD'])))
            ->get()->sum(fn (SalesOrderLine $line) => $this->topUp($line));
    }
}
