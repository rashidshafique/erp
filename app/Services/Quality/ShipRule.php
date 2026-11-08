<?php

namespace App\Services\Quality;

use App\Models\CastingLot;

/**
 * What a customer's order lets a lot do while pieces are held: whether the good ones may ship alone, and the fewest worth sending.
 * The customer sets whether partial shipments are allowed; an order line may override that and sets the minimum. A lot with no sales
 * line (stock, rework) keeps the plain rule: an open NCR holds the whole lot.
 */
class ShipRule
{
    /**
     * @return array{partial: bool, min: ?int, customer: ?string, order: ?string, override: bool}
     */
    public function forLot(CastingLot $lot): array
    {
        $line = $lot->productionOrder?->salesOrderLine;
        if (! $line) {
            return ['partial' => false, 'min' => null, 'customer' => null, 'order' => null, 'override' => false];
        }
        $line->loadMissing('salesOrder.customer');
        $customer = $line->salesOrder?->customer;

        return [
            'partial' => $line->partial_shipments ?? (bool) $customer?->allows_partial_shipments,
            'min' => $line->min_ship_qty ? (int) $line->min_ship_qty : null,
            'customer' => $customer?->name,
            'order' => $line->salesOrder?->order_number,
            'override' => $line->partial_shipments !== null,
        ];
    }

    /** Pieces passed at final inspection and not sent to rework or rejected. */
    public function accepted(CastingLot $lot): int
    {
        return (int) $lot->inspections()->where('inspection_type', 'FINAL')->whereIn('status', ['COMPLETED', 'APPROVED', 'CLOSED'])->sum('quantity_accepted');
    }

    public function openNcrs(CastingLot $lot): int
    {
        return $lot->ncrs()->where('status', '!=', 'CLOSED')->count();
    }

    /** Whether open NCRs leave the accepted pieces free to ship: partials allowed and some pieces accepted. */
    public function ncrLeavesAcceptedFree(CastingLot $lot): bool
    {
        return $this->forLot($lot)['partial'] && $this->accepted($lot) > 0;
    }

    /** Pieces of the lot that may ship now: all the good ones, or only the accepted ones while an NCR is open and partials are allowed. */
    public function shippable(CastingLot $lot): int
    {
        if ($this->openNcrs($lot) === 0) {
            return (int) $lot->quantity_good;
        }

        return $this->ncrLeavesAcceptedFree($lot) ? min((int) $lot->quantity_good, $this->accepted($lot)) : 0;
    }

    /**
     * The fewest pieces to ship from this lot: the line's minimum, never more than what the line still has to receive.
     */
    public function minimum(CastingLot $lot): ?int
    {
        $min = $this->forLot($lot)['min'];
        if (! $min) {
            return null;
        }
        $line = $lot->productionOrder?->salesOrderLine;
        $left = (int) ceil((float) $line->ordered_qty - (float) \App\Domains\Dispatch\Models\DispatchItem::where('sales_order_line_id', $line->id)->sum('quantity_dispatched'));

        return max(1, min($min, $left > 0 ? $left : $min));
    }
}
