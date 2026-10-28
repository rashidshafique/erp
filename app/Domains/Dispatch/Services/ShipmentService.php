<?php

namespace App\Domains\Dispatch\Services;

use App\Models\CastingLot;
use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Dispatch\Models\DispatchItem;
use App\Domains\Dispatch\Models\PackingRecord;
use App\Domains\Dispatch\Models\PackingRecordItem;
use App\Domains\Sales\Services\SalesOrderStatus;
use App\Services\Patterns\PatternOwnership;
use App\Services\QualityGates;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShipmentService
{
    public function __construct(private QualityGates $gates) {}

    public function packedQty(CastingLot $lot): float
    {
        return (float) PackingRecordItem::where('casting_lot_id', $lot->id)->sum('quantity_packed');
    }

    public function packable(CastingLot $lot): float
    {
        return max(0, (float) $lot->quantity_good - $this->packedQty($lot));
    }

    public function dispatchedQty(PackingRecordItem $item): float
    {
        return (float) DispatchItem::where('packing_record_item_id', $item->id)->sum('quantity_dispatched');
    }

    public function undispatched(PackingRecordItem $item): float
    {
        return max(0, (float) $item->quantity_packed - $this->dispatchedQty($item));
    }

    /**
     * @param  array<string,int|float>  $qtyByLot  lot id => quantity
     * @param  array<string,int|float|null>  $weighedByLot  lot id => net kg on the scale; a lot left out is estimated from its poured weight
     */
    public function pack(array $qtyByLot, array $head, array $weighedByLot = []): PackingRecord
    {
        $qtyByLot = array_filter($qtyByLot, fn ($q) => (float) $q > 0);
        if ($qtyByLot === []) {
            throw new InvalidArgumentException('Enter a quantity for at least one lot.');
        }

        return DB::transaction(function () use ($qtyByLot, $head, $weighedByLot) {
            $date = now()->format('ymd');
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['packing-number-'.$date]);
            $prefix = "PK-{$date}-";
            $n = (PackingRecord::where('packing_number', 'like', $prefix.'%')->pluck('packing_number')->map(fn ($x) => (int) substr($x, strlen($prefix)))->max() ?? 0) + 1;
            $pack = PackingRecord::create([
                'packing_number' => $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'status' => 'PACKED', 'packed_at' => now(),
                'packed_by' => $head['packed_by'] ?? null, 'checked_by' => $head['checked_by'] ?? null,
                'package_type' => $head['package_type'] ?? null, 'storage_location' => $head['storage_location'] ?? null, 'package_count' => (int) ($head['package_count'] ?? 1),
                'packaging_notes' => $head['packaging_notes'] ?? null,
            ]);
            $net = 0.0;
            foreach ($qtyByLot as $lotId => $qty) {
                $lot = CastingLot::whereKey($lotId)->lockForUpdate()->firstOrFail();
                $qty = (int) $qty;
                $ready = $this->gates->lotReadiness($lot);
                if (! $ready['ok']) {
                    throw new InvalidArgumentException("Lot {$lot->lot_number} is not cleared for packing: ".implode(' ', $ready['reasons']));
                }
                if (! in_array($lot->status, ['READY', 'PACKED'], true)) {
                    throw new InvalidArgumentException("Lot {$lot->lot_number} is {$lot->status}; only READY lots can be packed.");
                }
                $room = $this->packable($lot);
                if ($qty > $room) {
                    throw new InvalidArgumentException("Lot {$lot->lot_number}: only {$room} good piece(s) are left to pack.");
                }
                $each = $lot->quantity_poured > 0 ? (float) $lot->net_casting_weight_kg / $lot->quantity_poured : 0;
                $w = (float) ($weighedByLot[$lotId] ?? 0) > 0 ? round((float) $weighedByLot[$lotId], 3) : round($each * $qty, 3);
                $net += $w;
                PackingRecordItem::create(['packing_record_id' => $pack->id, 'casting_lot_id' => $lot->id, 'quantity_packed' => $qty, 'net_weight_kg' => $w, 'uom' => 'PCS']);
                if ($this->packable($lot) <= 0) {
                    $lot->update(['status' => 'PACKED']);
                }
            }
            $pack->update(['total_net_weight_kg' => round($net, 3)]);

            return $pack;
        });
    }

    /** @param array<string,int|float> $qtyByItem packing item id => quantity */
    public function dispatch(array $qtyByItem, array $head): Dispatch
    {
        $qtyByItem = array_filter($qtyByItem, fn ($q) => (float) $q > 0);
        if ($qtyByItem === []) {
            throw new InvalidArgumentException('Enter a quantity for at least one packed item.');
        }

        return DB::transaction(function () use ($qtyByItem, $head) {
            $date = now()->format('ymd');
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['dispatch-number-'.$date]);
            $prefix = "DSP-{$date}-";
            $n = (Dispatch::where('dispatch_number', 'like', $prefix.'%')->pluck('dispatch_number')->map(fn ($x) => (int) substr($x, strlen($prefix)))->max() ?? 0) + 1;
            $disp = Dispatch::create([
                'dispatch_number' => $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'customer_id' => $head['customer_id'], 'status' => 'DISPATCHED',
                'dispatched_at' => now(), 'delivery_challan_number' => $head['delivery_challan_number'] ?? null, 'carrier_name' => $head['carrier_name'] ?? null,
                'vehicle_number' => $head['vehicle_number'] ?? null, 'driver_name' => $head['driver_name'] ?? null,
                'delivery_address' => $head['delivery_address'] ?? null, 'dispatched_by' => $head['dispatched_by'] ?? null,
            ]);
            $net = 0.0;
            $packs = [];
            $orders = [];
            foreach ($qtyByItem as $itemId => $qty) {
                $item = PackingRecordItem::with('lot.productionOrder.salesOrderLine.salesOrder')->whereKey($itemId)->lockForUpdate()->firstOrFail();
                $lot = $item->lot;
                $qty = (int) $qty;
                if ($qty > $this->undispatched($item)) {
                    throw new InvalidArgumentException("Lot {$lot->lot_number}: only {$this->undispatched($item)} packed piece(s) are left to dispatch.");
                }
                $line = $lot->productionOrder?->salesOrderLine;
                if ($line && $line->salesOrder->customer_id !== $head['customer_id']) {
                    throw new InvalidArgumentException("Lot {$lot->lot_number} was made against another customer's order and cannot go on this dispatch.");
                }
                if ($lot->ncrs()->where('status', '!=', 'CLOSED')->exists()) {
                    throw new InvalidArgumentException("Lot {$lot->lot_number} has an open nonconformance report.");
                }
                $ready = $this->gates->lotReadiness($lot);
                if (! $ready['ok']) {
                    throw new InvalidArgumentException("Lot {$lot->lot_number} is not cleared for dispatch: ".implode(' ', $ready['reasons']));
                }
                $w = $item->quantity_packed > 0 ? round((float) $item->net_weight_kg * $qty / (float) $item->quantity_packed, 3) : 0;
                $net += $w;
                DispatchItem::create(['dispatch_id' => $disp->id, 'packing_record_item_id' => $item->id, 'sales_order_line_id' => $line?->id,
                    'quantity_dispatched' => $qty, 'net_weight_kg' => $w, 'uom' => 'PCS']);
                $packs[$item->packing_record_id] = true;
                if ($line) {
                    $orders[$line->sales_order_id] = $line->salesOrder;
                }
                $allGone = PackingRecordItem::where('casting_lot_id', $lot->id)->get()->every(fn ($i) => $this->undispatched($i) <= 0);
                if ($allGone && $this->packable($lot) <= 0) {
                    $lot->update(['status' => 'DISPATCHED']);
                }
            }
            foreach ($orders as $order) {
                app(SalesOrderStatus::class)->dispatched($order->load('lines'));
            }
            $disp->update(['total_net_weight_kg' => round($net, 3), 'total_packages' => count($packs)]);
            // Patterns this customer is paying off by castings may now be theirs.
            app(PatternOwnership::class)->dispatchedTo($disp->customer_id);

            return $disp;
        });
    }
}
